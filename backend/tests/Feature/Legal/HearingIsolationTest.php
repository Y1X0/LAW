<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\CaseAssignment;
use Modules\Legal\Models\Hearing;
use Modules\Legal\Models\LegalCase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * عزل رؤية الجلسات (LC-3) — يرث عزل القضية: المحامي يرى جلسات قضاياه فقط.
 */
class HearingIsolationTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** محامٍ (view_own) مرتبط بموظف، مع قضية مسندة وجلسة عليها. */
    private function lawyerWithCaseHearing(string $internal): array
    {
        $user = $this->userWithPermissions(['cases.view_own']);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $case = LegalCase::factory()->create(['internal_number' => $internal, 'responsible_lawyer_id' => $employee->id]);
        CaseAssignment::create(['case_id' => $case->id, 'employee_id' => $employee->id, 'role' => 'lead']);
        $hearing = Hearing::factory()->create(['case_id' => $case->id]);

        return [$user, $case, $hearing];
    }

    public function test_lawyer_sees_only_hearings_of_own_cases(): void
    {
        [$userA, , $hearingA] = $this->lawyerWithCaseHearing('A-1');
        $this->lawyerWithCaseHearing('B-1'); // جلسة المحامي B

        $this->actingAsToken($userA)->getJson('/api/hearings')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $hearingA->id);
    }

    public function test_lawyer_cannot_view_another_lawyers_hearing(): void
    {
        [$userA] = $this->lawyerWithCaseHearing('A-2');
        [, $caseB, $hearingB] = $this->lawyerWithCaseHearing('B-2');

        $this->actingAsToken($userA)->getJson("/api/hearings/{$hearingB->id}")
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'FORBIDDEN');

        // ولا عبر مسار جلسات القضية.
        $this->actingAsToken($userA)->getJson("/api/cases/{$caseB->id}/hearings")
            ->assertStatus(403);
    }

    public function test_admin_sees_all_hearings(): void
    {
        $this->lawyerWithCaseHearing('A-3');
        $this->lawyerWithCaseHearing('B-3');

        $admin = $this->userWithPermissions(['cases.view_all']);
        $this->actingAsToken($admin)->getJson('/api/hearings')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_view_own_without_linked_employee_is_forbidden(): void
    {
        $orphan = $this->userWithPermissions(['cases.view_own']);
        Hearing::factory()->create();

        $this->actingAsToken($orphan)->getJson('/api/hearings')
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'NO_LINKED_EMPLOYEE');
    }

    public function test_lawyer_can_view_own_case_hearing(): void
    {
        [$userA, $caseA, $hearingA] = $this->lawyerWithCaseHearing('A-4');

        $this->actingAsToken($userA)->getJson("/api/hearings/{$hearingA->id}")->assertOk();
        $this->actingAsToken($userA)->getJson("/api/cases/{$caseA->id}/hearings")
            ->assertOk()
            ->assertJsonPath('data.0.id', $hearingA->id);
    }
}
