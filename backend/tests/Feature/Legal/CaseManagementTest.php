<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\Client;
use Modules\Legal\Models\LegalCase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class CaseManagementTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        $client = Client::factory()->create();

        return array_merge([
            'internal_number' => 'CASE-2026-100',
            'court_case_number' => '2026/156',
            'title' => 'مطالبة مالية بموجب عقد توريد',
            'client_id' => $client->id,
            'case_type' => 'تجاري',
            'value' => 150000,
        ], $overrides);
    }

    public function test_can_create_case(): void
    {
        $admin = $this->userWithPermissions(['cases.create']);

        $this->actingAsToken($admin)
            ->postJson('/api/cases', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.internal_number', 'CASE-2026-100')
            ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('cases', ['internal_number' => 'CASE-2026-100', 'created_by' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'case_created']);
    }

    public function test_create_with_responsible_lawyer_registers_lead_assignment(): void
    {
        $admin = $this->userWithPermissions(['cases.create']);
        $lawyer = Employee::factory()->create();

        $response = $this->actingAsToken($admin)
            ->postJson('/api/cases', $this->payload(['responsible_lawyer_id' => $lawyer->id]))
            ->assertCreated();

        $caseId = $response->json('data.id');
        $this->assertDatabaseHas('case_assignments', [
            'case_id' => $caseId, 'employee_id' => $lawyer->id, 'role' => 'lead',
        ]);
    }

    public function test_can_list_and_show_cases_as_admin(): void
    {
        $admin = $this->userWithPermissions(['cases.view_all']);
        $case = LegalCase::factory()->create();

        $this->actingAsToken($admin)->getJson('/api/cases')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['page', 'per_page', 'total', 'total_pages'], 'errors']);

        $this->actingAsToken($admin)->getJson("/api/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $case->id);
    }

    public function test_can_update_case(): void
    {
        $editor = $this->userWithPermissions(['cases.update', 'cases.view_all']);
        $case = LegalCase::factory()->create(['title' => 'قديم', 'progress' => 10]);

        $this->actingAsToken($editor)->putJson("/api/cases/{$case->id}", ['title' => 'محدّث', 'progress' => 55])
            ->assertOk()
            ->assertJsonPath('data.title', 'محدّث')
            ->assertJsonPath('data.progress', 55);

        $this->assertDatabaseHas('audit_logs', ['action' => 'case_updated']);
    }

    /** L1 (B5 · PR-4): حامل cases.update بلا view_all وغير مُسنَد للقضيّة يُمنَع من تعديلها (IDOR). */
    public function test_case_update_denied_for_unassigned_user_without_view_all(): void
    {
        $outsider = $this->userWithPermissions(['cases.update', 'cases.view_own']);
        $case = LegalCase::factory()->create(['title' => 'قضيّة غير مُسنَدة']);

        $this->actingAsToken($outsider)->putJson("/api/cases/{$case->id}", ['title' => 'اختراق'])
            ->assertStatus(403);

        $this->assertDatabaseHas('cases', ['id' => $case->id, 'title' => 'قضيّة غير مُسنَدة']); // لم تتغيّر
    }

    public function test_can_assign_and_unassign_lawyer(): void
    {
        $assigner = $this->userWithPermissions(['cases.assign', 'cases.view_all']);
        $case = LegalCase::factory()->create();
        $lawyer = Employee::factory()->create();

        $this->actingAsToken($assigner)
            ->postJson("/api/cases/{$case->id}/assign", ['employee_id' => $lawyer->id, 'role' => 'support'])
            ->assertCreated();
        $this->assertDatabaseHas('case_assignments', ['case_id' => $case->id, 'employee_id' => $lawyer->id, 'role' => 'support']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'case_lawyer_assigned']);

        $this->actingAsToken($assigner)
            ->deleteJson("/api/cases/{$case->id}/assign/{$lawyer->id}")
            ->assertOk();
        $this->assertDatabaseMissing('case_assignments', ['case_id' => $case->id, 'employee_id' => $lawyer->id]);
    }

    public function test_can_close_case(): void
    {
        $closer = $this->userWithPermissions(['cases.close', 'cases.view_all']);
        $case = LegalCase::factory()->create(['status' => 'open']);

        $this->actingAsToken($closer)->postJson("/api/cases/{$case->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->assertDatabaseHas('cases', ['id' => $case->id, 'status' => 'closed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'case_closed']);
    }

    public function test_create_validates_input(): void
    {
        $admin = $this->userWithPermissions(['cases.create']);

        $this->actingAsToken($admin)
            ->postJson('/api/cases', $this->payload(['internal_number' => '', 'title' => '', 'client_id' => 99999]))
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_internal_number_must_be_unique(): void
    {
        $admin = $this->userWithPermissions(['cases.create']);
        LegalCase::factory()->create(['internal_number' => 'DUP-1']);

        $this->actingAsToken($admin)
            ->postJson('/api/cases', $this->payload(['internal_number' => 'DUP-1']))
            ->assertStatus(422);
    }

    public function test_create_requires_permission(): void
    {
        $viewer = $this->userWithPermissions(['cases.view_all']);

        $this->actingAsToken($viewer)
            ->postJson('/api/cases', $this->payload())
            ->assertStatus(403);
    }

    public function test_routes_require_authentication(): void
    {
        $this->getJson('/api/cases')->assertStatus(401);
        $this->postJson('/api/cases', $this->payload())->assertStatus(401);
    }
}
