<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\CaseTask;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** محامٍ (view_own) مرتبط بموظف، مع مهمة مسندة إليه. */
    private function lawyerWithTask(array $extraPerms = []): array
    {
        $user = $this->userWithPermissions(array_merge(['tasks.view_own'], $extraPerms));
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $task = CaseTask::factory()->create(['assigned_to' => $employee->id]);

        return [$user, $employee, $task];
    }

    public function test_can_create_and_assign_task(): void
    {
        $manager = $this->userWithPermissions(['tasks.create']);
        $lawyer = Employee::factory()->create();

        $this->actingAsToken($manager)
            ->postJson('/api/tasks', ['title' => 'إعداد مذكرة', 'assigned_to' => $lawyer->id, 'priority' => 'high'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'إعداد مذكرة')
            ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('case_tasks', ['title' => 'إعداد مذكرة', 'assigned_to' => $lawyer->id, 'created_by' => $manager->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'task_created']);
    }

    public function test_lawyer_sees_only_own_tasks(): void
    {
        [$userA, , $taskA] = $this->lawyerWithTask();
        [, , $taskB] = $this->lawyerWithTask();

        $this->actingAsToken($userA)->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $taskA->id);

        $this->actingAsToken($userA)->getJson("/api/tasks/{$taskB->id}")
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'FORBIDDEN');
        $this->actingAsToken($userA)->getJson("/api/tasks/{$taskA->id}")->assertOk();
    }

    public function test_admin_sees_all_tasks(): void
    {
        $this->lawyerWithTask();
        $this->lawyerWithTask();
        $admin = $this->userWithPermissions(['tasks.view_all']);

        $this->actingAsToken($admin)->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_view_own_without_linked_employee_is_forbidden(): void
    {
        $orphan = $this->userWithPermissions(['tasks.view_own']);
        CaseTask::factory()->create();

        $this->actingAsToken($orphan)->getJson('/api/tasks')
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'NO_LINKED_EMPLOYEE');
    }

    public function test_assignee_can_complete_task_and_records_completed_at(): void
    {
        [$userA, , $taskA] = $this->lawyerWithTask(['tasks.complete']);

        $this->actingAsToken($userA)->patchJson("/api/tasks/{$taskA->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'done');

        $task = CaseTask::find($taskA->id);
        $this->assertSame('done', $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'task_completed']);
    }

    public function test_non_assignee_cannot_complete_task(): void
    {
        [, , $taskA] = $this->lawyerWithTask();
        // محامٍ آخر يملك tasks.complete لكنه ليس المُسنَد إليه.
        $other = $this->userWithPermissions(['tasks.view_own', 'tasks.complete']);
        Employee::factory()->create(['user_id' => $other->id]);

        $this->actingAsToken($other)->patchJson("/api/tasks/{$taskA->id}/complete")
            ->assertStatus(403);
    }

    public function test_can_reassign_task(): void
    {
        $assigner = $this->userWithPermissions(['tasks.assign']);
        $task = CaseTask::factory()->create();
        $newLawyer = Employee::factory()->create();

        $this->actingAsToken($assigner)->patchJson("/api/tasks/{$task->id}/assign", ['employee_id' => $newLawyer->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_to', $newLawyer->id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'task_reassigned']);
    }

    public function test_create_requires_permission_and_validates(): void
    {
        $viewer = $this->userWithPermissions(['tasks.view_all']);
        $lawyer = Employee::factory()->create();
        $this->actingAsToken($viewer)
            ->postJson('/api/tasks', ['title' => 'x', 'assigned_to' => $lawyer->id])
            ->assertStatus(403);

        $creator = $this->userWithPermissions(['tasks.create']);
        $this->actingAsToken($creator)->postJson('/api/tasks', ['title' => ''])->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/tasks')->assertStatus(401);
    }
}
