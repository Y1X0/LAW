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

    // ---- عزل الكتابة (M1): store/update/postpone/cancel يرث نفس حارس عزل القضية ----

    /** حامل hearings.manage + cases.view_own مرتبط بموظف، مع قضية مسندة وجلسة عليها. */
    private function scopedHearingManager(string $internal): array
    {
        $user = $this->userWithPermissions(['hearings.manage', 'cases.view_own']);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $case = LegalCase::factory()->create(['internal_number' => $internal, 'responsible_lawyer_id' => $employee->id]);
        CaseAssignment::create(['case_id' => $case->id, 'employee_id' => $employee->id, 'role' => 'lead']);
        $hearing = Hearing::factory()->create(['case_id' => $case->id]);

        return [$user, $case, $hearing];
    }

    public function test_scoped_manager_cannot_write_hearings_outside_scope(): void
    {
        [$userA] = $this->scopedHearingManager('OUT-A');
        [, $caseB, $hearingB] = $this->scopedHearingManager('OUT-B');

        // store على قضية غير مسندة (الجسم صالح كي يصل إلى الحارس لا يُرَدّ 422).
        $this->actingAsToken($userA)->postJson("/api/cases/{$caseB->id}/hearings", [
            'scheduled_at' => now()->addDays(5)->toDateTimeString(), 'type' => 'مرافعة',
        ])->assertStatus(403)->assertJsonPath('errors.code', 'FORBIDDEN');

        $this->actingAsToken($userA)->putJson("/api/hearings/{$hearingB->id}", ['location' => 'اختراق'])
            ->assertStatus(403)->assertJsonPath('errors.code', 'FORBIDDEN');

        $this->actingAsToken($userA)->postJson("/api/hearings/{$hearingB->id}/postpone", [
            'scheduled_at' => now()->addDays(9)->toDateTimeString(), 'postponed_reason' => 'x',
        ])->assertStatus(403)->assertJsonPath('errors.code', 'FORBIDDEN');

        $this->actingAsToken($userA)->postJson("/api/hearings/{$hearingB->id}/cancel")
            ->assertStatus(403)->assertJsonPath('errors.code', 'FORBIDDEN');

        // لم تتغيّر جلسة B ولا أُنشئت جلسة على قضيته.
        $this->assertDatabaseHas('hearings', ['id' => $hearingB->id, 'status' => 'scheduled']);
        $this->assertDatabaseMissing('hearings', ['id' => $hearingB->id, 'location' => 'اختراق']);
        $this->assertDatabaseCount('hearings', 2); // جلستان فقط (A وB) — لا store ناجح.
    }

    public function test_scoped_manager_can_write_hearings_within_scope(): void
    {
        [$user, $case, $hearing] = $this->scopedHearingManager('IN-1');

        $this->actingAsToken($user)->postJson("/api/cases/{$case->id}/hearings", [
            'scheduled_at' => now()->addDays(5)->toDateTimeString(), 'type' => 'مرافعة',
        ])->assertCreated();

        $this->actingAsToken($user)->putJson("/api/hearings/{$hearing->id}", ['location' => 'قاعة 9'])
            ->assertOk()->assertJsonPath('data.location', 'قاعة 9');

        $toPostpone = Hearing::factory()->create(['case_id' => $case->id, 'scheduled_at' => now()->addDays(6)]);
        $this->actingAsToken($user)->postJson("/api/hearings/{$toPostpone->id}/postpone", [
            'scheduled_at' => now()->addDays(12)->toDateTimeString(), 'postponed_reason' => 'سبب',
        ])->assertCreated();

        $toCancel = Hearing::factory()->create(['case_id' => $case->id, 'scheduled_at' => now()->addDays(7)]);
        $this->actingAsToken($user)->postJson("/api/hearings/{$toCancel->id}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_manage_without_linked_employee_is_forbidden_on_write(): void
    {
        // hearings.manage بلا view_all وبلا موظف مرتبط → NO_LINKED_EMPLOYEE على الكتابة.
        $orphan = $this->userWithPermissions(['hearings.manage']);
        $case = LegalCase::factory()->create();

        $this->actingAsToken($orphan)->postJson("/api/cases/{$case->id}/hearings", [
            'scheduled_at' => now()->addDays(2)->toDateTimeString(),
        ])->assertStatus(403)->assertJsonPath('errors.code', 'NO_LINKED_EMPLOYEE');
    }
}
