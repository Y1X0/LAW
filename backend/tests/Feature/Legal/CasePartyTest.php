<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\CaseAssignment;
use Modules\Legal\Models\CaseParty;
use Modules\Legal\Models\LegalCase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class CasePartyTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** محامٍ (view_own) مرتبط بموظف مع قضية مسندة. */
    private function lawyerWithCase(string $internal): array
    {
        $user = $this->userWithPermissions(['cases.view_own']);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $case = LegalCase::factory()->create(['internal_number' => $internal, 'responsible_lawyer_id' => $employee->id]);
        CaseAssignment::create(['case_id' => $case->id, 'employee_id' => $employee->id, 'role' => 'lead']);

        return [$user, $case];
    }

    public function test_can_add_party_and_logs_timeline(): void
    {
        $manager = $this->userWithPermissions(['cases.update', 'cases.view_all']);
        $case = LegalCase::factory()->create();

        $this->actingAsToken($manager)
            ->postJson("/api/cases/{$case->id}/parties", ['name' => 'شركة ABC', 'type' => 'defendant', 'phone' => '0791111111'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'شركة ABC')
            ->assertJsonPath('data.type', 'defendant');

        $this->assertDatabaseHas('case_parties', ['case_id' => $case->id, 'name' => 'شركة ABC', 'type' => 'defendant']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'case_party_added']);
        // إضافة الطرف تُسجَّل تلقائياً في الخط الزمني بمفتاح آلي.
        $this->assertDatabaseHas('case_timeline_events', ['case_id' => $case->id, 'event_type' => 'party_added']);
    }

    public function test_lawyer_sees_only_own_case_parties(): void
    {
        [$userA, $caseA] = $this->lawyerWithCase('A-1');
        [, $caseB] = $this->lawyerWithCase('B-1');
        CaseParty::factory()->create(['case_id' => $caseA->id, 'name' => 'طرف A']);
        CaseParty::factory()->create(['case_id' => $caseB->id, 'name' => 'طرف B']);

        $this->actingAsToken($userA)->getJson("/api/cases/{$caseA->id}/parties")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'طرف A');

        $this->actingAsToken($userA)->getJson("/api/cases/{$caseB->id}/parties")
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'FORBIDDEN');
    }

    public function test_admin_sees_all_case_parties(): void
    {
        $case = LegalCase::factory()->create();
        CaseParty::factory()->count(2)->create(['case_id' => $case->id]);
        $admin = $this->userWithPermissions(['cases.view_all']);

        $this->actingAsToken($admin)->getJson("/api/cases/{$case->id}/parties")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_add_party_requires_update_permission_and_validates(): void
    {
        $viewer = $this->userWithPermissions(['cases.view_all']);
        $case = LegalCase::factory()->create();
        $this->actingAsToken($viewer)
            ->postJson("/api/cases/{$case->id}/parties", ['name' => 'x', 'type' => 'defendant'])
            ->assertStatus(403);

        $manager = $this->userWithPermissions(['cases.update', 'cases.view_all']);
        $this->actingAsToken($manager)
            ->postJson("/api/cases/{$case->id}/parties", ['name' => '', 'type' => 'unknown'])
            ->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $case = LegalCase::factory()->create();
        $this->getJson("/api/cases/{$case->id}/parties")->assertStatus(401);
    }
}
