<?php

namespace Tests\Feature\Leave;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function annualType(array $overrides = []): LeaveType
    {
        return LeaveType::create(array_merge([
            'name' => 'سنوية', 'code' => 'annual', 'is_paid' => true,
            'consumes_balance' => true, 'requires_attachment' => false, 'default_annual_days' => 20,
        ], $overrides));
    }

    private function grantBalance(Employee $e, LeaveType $t, float $entitled, int $year = 2026): LeaveBalance
    {
        return LeaveBalance::create([
            'employee_id' => $e->id, 'leave_type_id' => $t->id, 'year' => $year, 'entitled_days' => $entitled,
        ]);
    }

    public function test_request_creates_pending_and_counts_working_days(): void
    {
        $actor = $this->userWithPermissions(['leaves.request']);
        $employee = Employee::factory()->create();
        $type = $this->annualType();
        $this->grantBalance($employee, $type, 20);

        // 2026-06-07 (أحد) → 2026-06-11 (خميس) = 5 أيام عمل، بلا عطلة.
        $res = $this->actingAsToken($actor)->postJson('/api/leave-requests', [
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-06-07', 'end_date' => '2026-06-11', 'reason' => 'سفر',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.days', 5)
            ->assertJsonPath('data.is_escalated', false);

        $this->assertDatabaseHas('leave_requests', ['employee_id' => $employee->id, 'status' => 'pending']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'leave_requested']);
    }

    public function test_working_days_exclude_weekend(): void
    {
        $actor = $this->userWithPermissions(['leaves.request']);
        $employee = Employee::factory()->create();
        $type = $this->annualType();
        $this->grantBalance($employee, $type, 20);

        // 2026-06-07 (أحد) → 2026-06-13 (سبت): تُستبعد الجمعة 12 والسبت 13 → 5 أيام.
        $this->actingAsToken($actor)->postJson('/api/leave-requests', [
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-06-07', 'end_date' => '2026-06-13',
        ])->assertCreated()->assertJsonPath('data.days', 5);
    }

    public function test_request_requires_permission(): void
    {
        $viewer = $this->userWithPermissions(['leaves.view_all']);
        $employee = Employee::factory()->create();
        $type = $this->annualType();

        $this->actingAsToken($viewer)->postJson('/api/leave-requests', [
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-06-07', 'end_date' => '2026-06-11',
        ])->assertStatus(403);
    }

    public function test_overlapping_request_is_rejected(): void
    {
        $actor = $this->userWithPermissions(['leaves.request']);
        $employee = Employee::factory()->create();
        $type = $this->annualType();
        $this->grantBalance($employee, $type, 20);

        LeaveRequest::create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-06-07', 'end_date' => '2026-06-11', 'days' => 5, 'status' => 'approved',
        ]);

        $this->actingAsToken($actor)->postJson('/api/leave-requests', [
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-06-10', 'end_date' => '2026-06-14', // يتداخل
        ])->assertStatus(422)->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_insufficient_balance_is_rejected(): void
    {
        $actor = $this->userWithPermissions(['leaves.request']);
        $employee = Employee::factory()->create();
        $type = $this->annualType();
        $this->grantBalance($employee, $type, 2); // رصيد 2 فقط

        $this->actingAsToken($actor)->postJson('/api/leave-requests', [
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-06-07', 'end_date' => '2026-06-11', // 5 أيام
        ])->assertStatus(422);
    }

    public function test_attachment_required_type_blocks_without_attachment(): void
    {
        $actor = $this->userWithPermissions(['leaves.request']);
        $employee = Employee::factory()->create();
        $sick = $this->annualType(['name' => 'مرضية', 'code' => 'sick', 'requires_attachment' => true]);
        $this->grantBalance($employee, $sick, 20);

        $this->actingAsToken($actor)->postJson('/api/leave-requests', [
            'employee_id' => $employee->id, 'leave_type_id' => $sick->id,
            'start_date' => '2026-06-07', 'end_date' => '2026-06-08',
        ])->assertStatus(422);

        $this->actingAsToken($actor)->postJson('/api/leave-requests', [
            'employee_id' => $employee->id, 'leave_type_id' => $sick->id,
            'start_date' => '2026-06-07', 'end_date' => '2026-06-08', 'attachment_path' => 'reports/med.pdf',
        ])->assertCreated();
    }

    public function test_cross_year_request_is_rejected(): void
    {
        $actor = $this->userWithPermissions(['leaves.request']);
        $employee = Employee::factory()->create();
        $type = $this->annualType();
        $this->grantBalance($employee, $type, 20);
        $this->grantBalance($employee, $type, 20, 2027);

        $this->actingAsToken($actor)->postJson('/api/leave-requests', [
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-12-30', 'end_date' => '2027-01-05',
        ])->assertStatus(422);
    }

    public function test_manager_request_is_escalated(): void
    {
        $actor = $this->userWithPermissions(['leaves.request']);
        $manager = Employee::factory()->create();
        Employee::factory()->create(['manager_id' => $manager->id]); // للمدير مرؤوس
        $type = $this->annualType();
        $this->grantBalance($manager, $type, 20);

        $this->actingAsToken($actor)->postJson('/api/leave-requests', [
            'employee_id' => $manager->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-06-07', 'end_date' => '2026-06-08',
        ])->assertCreated()->assertJsonPath('data.is_escalated', true);
    }

    public function test_unpaid_type_skips_balance_requirement(): void
    {
        $actor = $this->userWithPermissions(['leaves.request']);
        $employee = Employee::factory()->create();
        $unpaid = $this->annualType(['name' => 'بدون راتب', 'code' => 'unpaid', 'is_paid' => false, 'consumes_balance' => false]);
        // لا رصيد ممنوح

        $this->actingAsToken($actor)->postJson('/api/leave-requests', [
            'employee_id' => $employee->id, 'leave_type_id' => $unpaid->id,
            'start_date' => '2026-06-07', 'end_date' => '2026-06-11',
        ])->assertCreated()->assertJsonPath('data.days', 5);
    }
}
