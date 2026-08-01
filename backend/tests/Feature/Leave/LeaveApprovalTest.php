<?php

namespace Tests\Feature\Leave;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\HR\Models\Employee;
use Modules\Leave\Events\LeaveApproved;
use Modules\Leave\Events\LeaveCancelled;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveService;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class LeaveApprovalTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function scenario(): array
    {
        $employee = Employee::factory()->create();
        $type = LeaveType::create([
            'name' => 'سنوية', 'code' => 'annual', 'is_paid' => true,
            'consumes_balance' => true, 'requires_attachment' => false, 'default_annual_days' => 20,
        ]);
        $balance = LeaveBalance::create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => 2026, 'entitled_days' => 20,
        ]);
        $request = LeaveRequest::create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-06-07', 'end_date' => '2026-06-11', 'days' => 5, 'status' => 'pending',
        ]);

        return [$employee, $type, $balance, $request];
    }

    public function test_approve_deducts_balance_and_dispatches_event(): void
    {
        Event::fake([LeaveApproved::class]);
        $approver = $this->userWithPermissions(['leaves.approve']);
        [$employee, $type, $balance, $request] = $this->scenario();

        $this->actingAsToken($approver)->postJson("/api/leave-requests/{$request->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertSame(5.0, (float) $balance->fresh()->consumed_days);
        $this->assertSame(15.0, (float) $balance->fresh()->remaining_days);
        Event::assertDispatched(LeaveApproved::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'leave_approved']);
    }

    public function test_reject_requires_reason_and_records_it(): void
    {
        $approver = $this->userWithPermissions(['leaves.approve']);
        [, , $balance, $request] = $this->scenario();

        // بلا سبب → 422
        $this->actingAsToken($approver)->postJson("/api/leave-requests/{$request->id}/reject")
            ->assertStatus(422);

        $this->actingAsToken($approver)->postJson("/api/leave-requests/{$request->id}/reject", [
            'reason' => 'ضغط عمل',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');

        // الرفض لا يخصم رصيداً
        $this->assertSame(0.0, (float) $balance->fresh()->consumed_days);
        $this->assertDatabaseHas('leave_requests', ['id' => $request->id, 'rejection_reason' => 'ضغط عمل']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'leave_rejected']);
    }

    public function test_cancel_approved_restores_balance(): void
    {
        Event::fake([LeaveApproved::class, LeaveCancelled::class]);
        $approver = $this->userWithPermissions(['leaves.approve']);
        [, , $balance, $request] = $this->scenario();

        $this->actingAsToken($approver)->postJson("/api/leave-requests/{$request->id}/approve")->assertOk();
        $this->assertSame(5.0, (float) $balance->fresh()->consumed_days);

        $this->actingAsToken($approver)->postJson("/api/leave-requests/{$request->id}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        // أُعيد الرصيد
        $this->assertSame(0.0, (float) $balance->fresh()->consumed_days);
        Event::assertDispatched(LeaveCancelled::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'leave_cancelled']);
    }

    /**
     * SEC-5: إلغاءان متزامنان (نسختان حُمِّلتا وهما 'approved') يجب ألا يخصما الرصيد
     * مرّتين — الفحص داخل القفل بعد refresh يمنع الاستعادة المزدوجة.
     */
    public function test_concurrent_cancel_does_not_double_refund_balance(): void
    {
        Event::fake([LeaveCancelled::class]);
        [, , $balance, $request] = $this->scenario();
        // الحالة كما بعد approve: معتمد وقد خُصم الرصيد.
        $request->update(['status' => 'approved']);
        $balance->update(['consumed_days' => 5]);

        // محاكاة تزامن: نسختان من الطلب حُمِّلتا معاً بينما الحالة 'approved'.
        $a = LeaveRequest::find($request->id);
        $b = LeaveRequest::find($request->id);

        $service = app(LeaveService::class);
        $req = Request::create('/');

        $service->cancel($a, $req); // إلغاء أول → يعيد 5 (consumed_days = 0)

        // الإلغاء الثاني على النسخة القديمة يُرفَض داخل القفل بلا خصم مزدوج.
        try {
            $service->cancel($b, $req);
            $this->fail('كان يجب رفض الإلغاء الثاني (الطلب أُلغي مسبقاً).');
        } catch (ValidationException) {
            // متوقّع
        }

        $this->assertSame(
            0.0,
            (float) $balance->fresh()->consumed_days,
            'يجب استعادة الرصيد مرّة واحدة فقط — لا خصم/استعادة مزدوجة.'
        );
    }

    public function test_approve_requires_approve_permission(): void
    {
        $requester = $this->userWithPermissions(['leaves.request']);
        [, , , $request] = $this->scenario();

        $this->actingAsToken($requester)->postJson("/api/leave-requests/{$request->id}/approve")
            ->assertStatus(403);
    }

    public function test_cannot_approve_non_pending_request(): void
    {
        $approver = $this->userWithPermissions(['leaves.approve']);
        [, , , $request] = $this->scenario();

        $this->actingAsToken($approver)->postJson("/api/leave-requests/{$request->id}/approve")->assertOk();
        // اعتماد ثانٍ → 422
        $this->actingAsToken($approver)->postJson("/api/leave-requests/{$request->id}/approve")->assertStatus(422);
    }

    public function test_submitter_cannot_approve_own_request(): void
    {
        // نفس المستخدم يملك تقديم واعتماد، لكنه لا يعتمد ما قدّمه بنفسه (فصل المهام).
        $actor = $this->userWithPermissions(['leaves.request', 'leaves.approve']);
        $employee = Employee::factory()->create();
        $type = LeaveType::create([
            'name' => 'سنوية', 'code' => 'annual', 'is_paid' => true,
            'consumes_balance' => true, 'requires_attachment' => false, 'default_annual_days' => 20,
        ]);
        LeaveBalance::create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => 2026, 'entitled_days' => 20]);

        $created = $this->actingAsToken($actor)->postJson('/api/leave-requests', [
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-06-07', 'end_date' => '2026-06-08',
        ])->assertCreated()->json('data.id');

        $this->actingAsToken($actor)->postJson("/api/leave-requests/{$created}/approve")
            ->assertStatus(403);
        $this->assertDatabaseHas('leave_requests', ['id' => $created, 'status' => 'pending']);
    }

    public function test_can_list_and_filter_requests(): void
    {
        $viewer = $this->userWithPermissions(['leaves.view_all']);
        [, , , $request] = $this->scenario();

        $this->actingAsToken($viewer)->getJson('/api/leave-requests')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['page', 'per_page', 'total', 'total_pages'], 'errors']);

        $res = $this->actingAsToken($viewer)->getJson('/api/leave-requests?status=pending')->assertOk();
        $this->assertSame(1, $res->json('meta.total'));
    }
}
