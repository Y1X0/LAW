<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\CaseArchiveLocation;
use Modules\Legal\Models\CaseAssignment;
use Modules\Legal\Models\LegalCase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** محامٍ (archive.view) مرتبط بموظف مع قضية مسندة. */
    private function lawyerWithCase(string $internal): array
    {
        $user = $this->userWithPermissions(['archive.view']);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $case = LegalCase::factory()->create(['internal_number' => $internal, 'responsible_lawyer_id' => $employee->id]);
        CaseAssignment::create(['case_id' => $case->id, 'employee_id' => $employee->id, 'role' => 'lead']);

        return [$user, $case];
    }

    public function test_can_add_multiple_locations_to_a_case(): void
    {
        $manager = $this->userWithPermissions(['archive.create', 'cases.view_all']);
        $case = LegalCase::factory()->create();

        $this->actingAsToken($manager)
            ->postJson("/api/cases/{$case->id}/archive-locations", ['file_title' => 'الملف الأصلي', 'archive_room' => 'غرفة 1', 'cabinet' => 'A', 'shelf' => '3', 'file_number' => '20'])
            ->assertCreated()->assertJsonPath('data.file_title', 'الملف الأصلي');
        $this->actingAsToken($manager)
            ->postJson("/api/cases/{$case->id}/archive-locations", ['file_title' => 'العقود', 'archive_room' => 'غرفة 2', 'cabinet' => 'C', 'drawer' => '1', 'file_number' => '5'])
            ->assertCreated();

        $this->assertDatabaseCount('case_archive_locations', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'archive_location_added']);
    }

    public function test_lawyer_sees_only_own_case_archive(): void
    {
        [$userA, $caseA] = $this->lawyerWithCase('A-1');
        [, $caseB] = $this->lawyerWithCase('B-1');
        CaseArchiveLocation::factory()->create(['case_id' => $caseA->id, 'file_title' => 'ملف A']);
        CaseArchiveLocation::factory()->create(['case_id' => $caseB->id, 'file_title' => 'ملف B']);

        $this->actingAsToken($userA)->getJson("/api/cases/{$caseA->id}/archive-locations")
            ->assertOk()->assertJsonPath('data.0.file_title', 'ملف A');

        $this->actingAsToken($userA)->getJson("/api/cases/{$caseB->id}/archive-locations")
            ->assertStatus(403)->assertJsonPath('errors.code', 'FORBIDDEN');
    }

    public function test_admin_sees_all_case_archive(): void
    {
        $case = LegalCase::factory()->create();
        CaseArchiveLocation::factory()->count(3)->create(['case_id' => $case->id]);
        $admin = $this->userWithPermissions(['archive.view', 'cases.view_all']);

        $this->actingAsToken($admin)->getJson("/api/cases/{$case->id}/archive-locations")
            ->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_can_update_location(): void
    {
        $manager = $this->userWithPermissions(['archive.update', 'cases.view_all']);
        $location = CaseArchiveLocation::factory()->create(['shelf' => '1']);

        $this->actingAsToken($manager)->putJson("/api/archive-locations/{$location->id}", ['shelf' => '9'])
            ->assertOk()->assertJsonPath('data.shelf', '9');
        $this->assertDatabaseHas('audit_logs', ['action' => 'archive_location_updated']);
    }

    public function test_delete_requires_delete_permission(): void
    {
        $case = LegalCase::factory()->create();
        $location = CaseArchiveLocation::factory()->create(['case_id' => $case->id]);

        // بصلاحية تعديل فقط (لا حذف) → 403 من middleware المسار.
        $updater = $this->userWithPermissions(['archive.update', 'cases.view_all']);
        $this->actingAsToken($updater)->deleteJson("/api/archive-locations/{$location->id}")->assertStatus(403);
        $this->assertDatabaseHas('case_archive_locations', ['id' => $location->id]);

        // بصلاحية الحذف → ينجح.
        $deleter = $this->userWithPermissions(['archive.delete', 'cases.view_all']);
        $this->actingAsToken($deleter)->deleteJson("/api/archive-locations/{$location->id}")->assertOk();
        $this->assertDatabaseMissing('case_archive_locations', ['id' => $location->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'archive_location_deleted']);
    }

    public function test_add_requires_create_permission_and_validates(): void
    {
        $case = LegalCase::factory()->create();
        $viewer = $this->userWithPermissions(['archive.view', 'cases.view_all']);
        $this->actingAsToken($viewer)
            ->postJson("/api/cases/{$case->id}/archive-locations", ['file_title' => 'x'])
            ->assertStatus(403);

        $creator = $this->userWithPermissions(['archive.create', 'cases.view_all']);
        $this->actingAsToken($creator)
            ->postJson("/api/cases/{$case->id}/archive-locations", ['file_title' => ''])
            ->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $case = LegalCase::factory()->create();
        $this->getJson("/api/cases/{$case->id}/archive-locations")->assertStatus(401);
    }
}
