<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\CaseAssignment;
use Modules\Legal\Models\CaseTask;
use Modules\Legal\Models\CaseTimelineEvent;
use Modules\Legal\Models\DailyWorklog;
use Modules\Legal\Models\Hearing;
use Modules\Legal\Models\LegalCase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class LawyerDashboardTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function lawyer(): array
    {
        $user = $this->userWithPermissions(['cases.view_own']);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        return [$user, $employee];
    }

    /** ينشئ قضية بحالة ويُسندها لموظف. */
    private function assignedCase(Employee $employee, string $status): LegalCase
    {
        $case = LegalCase::factory()->create(['status' => $status, 'responsible_lawyer_id' => $employee->id]);
        CaseAssignment::create(['case_id' => $case->id, 'employee_id' => $employee->id, 'role' => 'lead']);

        return $case;
    }

    public function test_summary_aggregates_only_own_data(): void
    {
        [$userA, $empA] = $this->lawyer();

        // قضايا A: 2 مفتوحة، 1 معلّقة، 1 مغلقة.
        $openCase = $this->assignedCase($empA, 'open');
        $this->assignedCase($empA, 'open');
        $this->assignedCase($empA, 'pending');
        $this->assignedCase($empA, 'closed');

        // مهام A: 2 مفتوحة + 1 منجزة.
        CaseTask::factory()->count(2)->create(['assigned_to' => $empA->id, 'status' => 'open']);
        CaseTask::factory()->done()->create(['assigned_to' => $empA->id]);

        // جلسة A القادمة (بعد 5 أيام) + حدثان على قضاياه.
        $nextHearing = Hearing::factory()->create(['case_id' => $openCase->id, 'scheduled_at' => now()->addDays(5), 'status' => 'scheduled']);
        CaseTimelineEvent::factory()->create(['case_id' => $openCase->id]);
        DailyWorklog::factory()->create(['employee_id' => $empA->id, 'done_today' => 'مراجعة قضية']);

        // بيانات محامٍ آخر B — بما فيها جلسة أبكر (غداً) يجب ألا تظهر لـ A.
        [, $empB] = $this->lawyer();
        $caseB = $this->assignedCase($empB, 'open');
        Hearing::factory()->create(['case_id' => $caseB->id, 'scheduled_at' => now()->addDay(), 'status' => 'scheduled']);
        CaseTask::factory()->create(['assigned_to' => $empB->id, 'status' => 'open']);

        $this->actingAsToken($userA)->getJson('/api/me/legal-summary')
            ->assertOk()
            ->assertJsonPath('data.cases.total', 4)
            ->assertJsonPath('data.cases.open', 2)
            ->assertJsonPath('data.cases.pending', 1)
            ->assertJsonPath('data.cases.closed', 1)
            ->assertJsonPath('data.tasks.pending', 2)
            ->assertJsonPath('data.next_hearing.id', $nextHearing->id) // جلسة A، وليست جلسة B الأبكر
            ->assertJsonPath('data.last_worklog.done_today', 'مراجعة قضية')
            ->assertJsonCount(1, 'data.recent_events');
    }

    public function test_empty_summary_for_lawyer_without_cases(): void
    {
        [$userA] = $this->lawyer();

        $this->actingAsToken($userA)->getJson('/api/me/legal-summary')
            ->assertOk()
            ->assertJsonPath('data.cases.total', 0)
            ->assertJsonPath('data.tasks.pending', 0)
            ->assertJsonPath('data.next_hearing', null)
            ->assertJsonPath('data.last_worklog', null)
            ->assertJsonCount(0, 'data.recent_events');
    }

    public function test_requires_linked_employee(): void
    {
        $orphan = $this->userWithPermissions(['cases.view_own']);

        $this->actingAsToken($orphan)->getJson('/api/me/legal-summary')
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'NO_LINKED_EMPLOYEE');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/me/legal-summary')->assertStatus(401);
    }
}
