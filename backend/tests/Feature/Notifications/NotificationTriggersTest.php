<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\HR\Models\Employee;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Modules\Legal\Models\CaseTask;
use Modules\Legal\Services\TaskService;
use Modules\Notifications\Models\Notification;
use Modules\Notifications\Services\NotificationService;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * ربط مُطلِقات الإشعارات بالأحداث القائمة (Phase 8 · PR-2) — بجوار recordAudit، دون
 * تغيير منطق الأعمال: قرار الإجازة → مقدّمها؛ إسناد/إكمال المهمة → المُسنَد إليه/مُنشئها.
 */
class NotificationTriggersTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** يبني طلب إجازة معلّقاً لموظف مرتبط بحساب مستخدم؛ يعيد [مستخدم المقدّم، الطلب]. */
    private function pendingLeave(): array
    {
        $requester = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $requester->id]);
        $type = LeaveType::create([
            'name' => 'سنوية', 'code' => 'annual', 'is_paid' => true,
            'consumes_balance' => true, 'requires_attachment' => false, 'default_annual_days' => 20,
        ]);
        LeaveBalance::create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => 2026, 'entitled_days' => 20]);
        $leave = LeaveRequest::create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-06-07', 'end_date' => '2026-06-11', 'days' => 5, 'status' => 'pending',
        ]);

        return [$requester, $leave];
    }

    private function requestAs(User $user): Request
    {
        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function test_leave_approval_notifies_requester(): void
    {
        [$requester, $leave] = $this->pendingLeave();
        $approver = $this->userWithPermissions(['leaves.approve']);

        $this->actingAsToken($approver)->postJson("/api/leave-requests/{$leave->id}/approve")->assertOk();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $requester->id, 'type' => 'leave_approved',
            'related_type' => 'LeaveRequest', 'related_id' => $leave->id, 'read_at' => null,
        ]);
    }

    public function test_leave_rejection_notifies_requester_with_reason(): void
    {
        [$requester, $leave] = $this->pendingLeave();
        $approver = $this->userWithPermissions(['leaves.approve']);

        $this->actingAsToken($approver)->postJson("/api/leave-requests/{$leave->id}/reject", ['reason' => 'ضغط عمل'])->assertOk();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $requester->id, 'type' => 'leave_rejected', 'body' => 'ضغط عمل',
            'related_type' => 'LeaveRequest', 'related_id' => $leave->id,
        ]);
    }

    public function test_leave_notification_recipient_without_login_account_is_skipped(): void
    {
        // موظف بلا حساب دخول (user_id = null) → لا مستلِم، لا إشعار، والعملية تنجح.
        $employee = Employee::factory()->create(['user_id' => null]);
        $type = LeaveType::create([
            'name' => 'سنوية', 'code' => 'annual', 'is_paid' => true,
            'consumes_balance' => false, 'requires_attachment' => false, 'default_annual_days' => 20,
        ]);
        $leave = LeaveRequest::create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-06-07', 'end_date' => '2026-06-11', 'days' => 5, 'status' => 'pending',
        ]);
        $approver = $this->userWithPermissions(['leaves.approve']);

        $this->actingAsToken($approver)->postJson("/api/leave-requests/{$leave->id}/approve")->assertOk();
        $this->assertSame(0, Notification::query()->where('type', 'leave_approved')->count());
    }

    public function test_task_assignment_notifies_assignee(): void
    {
        $assigneeUser = User::factory()->create();
        $assignee = Employee::factory()->create(['user_id' => $assigneeUser->id]);
        $task = CaseTask::factory()->create();
        $actor = User::factory()->create();

        app(TaskService::class)->assign($task, $assignee->id, $this->requestAs($actor));

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $assigneeUser->id, 'type' => 'task_assigned',
            'related_type' => 'CaseTask', 'related_id' => $task->id,
        ]);
    }

    public function test_task_completion_notifies_creator(): void
    {
        $creator = User::factory()->create();
        $task = CaseTask::factory()->create(['created_by' => $creator->id]);
        $completer = User::factory()->create();

        app(TaskService::class)->complete($task, $this->requestAs($completer));

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $creator->id, 'type' => 'task_completed',
            'related_type' => 'CaseTask', 'related_id' => $task->id,
        ]);
    }

    public function test_no_self_notification_when_completer_is_creator(): void
    {
        $creator = User::factory()->create();
        $task = CaseTask::factory()->create(['created_by' => $creator->id]);

        // المُنشئ نفسه يُكمل مهمته → لا إشعار ذاتي.
        app(TaskService::class)->complete($task, $this->requestAs($creator));
        $this->assertSame(0, Notification::query()->where('type', 'task_completed')->count());
    }

    public function test_blocked_reapproval_does_not_create_duplicate_notification(): void
    {
        [$requester, $leave] = $this->pendingLeave();
        $approver = $this->userWithPermissions(['leaves.approve']);

        $this->actingAsToken($approver)->postJson("/api/leave-requests/{$leave->id}/approve")->assertOk();
        // اعتماد ثانٍ يُرفَض (422) قبل الإطلاق → يبقى إشعار واحد فقط.
        $this->actingAsToken($approver)->postJson("/api/leave-requests/{$leave->id}/approve")->assertStatus(422);

        $this->assertSame(
            1,
            Notification::query()->where('user_id', $requester->id)->where('type', 'leave_approved')->count(),
        );
    }

    public function test_notification_failure_does_not_break_the_operation(): void
    {
        // خدمة إشعارات معطوبة (ترمي) → العملية الأساسية تنجح والإشعار يُبتلَع (best-effort).
        $this->app->bind(NotificationService::class, fn () => new class extends NotificationService
        {
            public function emit(int $userId, string $type, string $title, ?string $body = null, ?string $relatedType = null, ?int $relatedId = null): Notification
            {
                throw new \RuntimeException('boom');
            }
        });

        [, $leave] = $this->pendingLeave();
        $approver = $this->userWithPermissions(['leaves.approve']);

        $this->actingAsToken($approver)->postJson("/api/leave-requests/{$leave->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'approved']);
        $this->assertSame(0, Notification::query()->count());
    }
}
