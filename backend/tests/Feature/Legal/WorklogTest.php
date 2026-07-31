<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\DailyWorklog;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class WorklogTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** محامٍ ذاتي: مستخدم بصلاحيات worklog مرتبط بموظف. */
    private function lawyer(array $perms): array
    {
        $user = $this->userWithPermissions($perms);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        return [$user, $employee];
    }

    public function test_can_submit_today_worklog(): void
    {
        [$user, $employee] = $this->lawyer(['worklog.submit_own']);

        $this->actingAsToken($user)
            ->postJson('/api/me/worklog', ['done_today' => 'مراجعة القضية 2024/156', 'plan_tomorrow' => 'إعداد مذكرة'])
            ->assertCreated()
            ->assertJsonPath('data.done_today', 'مراجعة القضية 2024/156');

        $this->assertDatabaseHas('daily_worklogs', ['employee_id' => $employee->id, 'work_date' => now()->toDateString()]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'worklog_submitted']);
    }

    public function test_resubmitting_today_updates_single_record(): void
    {
        [$user, $employee] = $this->lawyer(['worklog.submit_own']);

        $this->actingAsToken($user)->postJson('/api/me/worklog', ['done_today' => 'أول'])->assertCreated();
        $this->actingAsToken($user)->postJson('/api/me/worklog', ['done_today' => 'محدّث'])->assertCreated();

        $this->assertDatabaseCount('daily_worklogs', 1);
        $this->assertDatabaseHas('daily_worklogs', ['employee_id' => $employee->id, 'done_today' => 'محدّث']);
    }

    public function test_lawyer_sees_only_own_worklog(): void
    {
        [$userA, $empA] = $this->lawyer(['worklog.view_own']);
        $other = Employee::factory()->create();
        DailyWorklog::factory()->create(['employee_id' => $empA->id, 'work_date' => now()->subDay()->toDateString(), 'done_today' => 'سجل A']);
        DailyWorklog::factory()->create(['employee_id' => $other->id, 'done_today' => 'سجل الغير']);

        $this->actingAsToken($userA)->getJson('/api/me/worklog')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.done_today', 'سجل A');
    }

    public function test_self_worklog_requires_linked_employee(): void
    {
        $orphan = $this->userWithPermissions(['worklog.submit_own', 'worklog.view_own']);

        $this->actingAsToken($orphan)->getJson('/api/me/worklog')
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'NO_LINKED_EMPLOYEE');
        $this->actingAsToken($orphan)->postJson('/api/me/worklog', ['done_today' => 'x'])
            ->assertStatus(403);
    }

    public function test_admin_can_view_all_worklogs(): void
    {
        DailyWorklog::factory()->create();
        DailyWorklog::factory()->create();
        $admin = $this->userWithPermissions(['worklog.view_all']);

        $this->actingAsToken($admin)->getJson('/api/worklog')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_submit_validates_input(): void
    {
        [$user] = $this->lawyer(['worklog.submit_own']);

        $this->actingAsToken($user)->postJson('/api/me/worklog', ['done_today' => ''])
            ->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/me/worklog')->assertStatus(401);
        $this->getJson('/api/worklog')->assertStatus(401);
    }
}
