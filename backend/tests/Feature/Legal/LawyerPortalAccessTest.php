<?php

namespace Tests\Feature\Legal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Seeders\RbacSeeder;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\CaseAssignment;
use Modules\Legal\Models\LegalCase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * تحقّق «تمكين الـ Pilot» (PR-1): مستخدم بدور «محامٍ» مسنَد من الـ Seeder — دون حقن صلاحيات
 * مباشر — يدخل ويصل إلى لوحته وقضاياه المسندة فقط، مع بقاء العزل على الخادم.
 */
class LawyerPortalAccessTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** ينشئ مستخدماً بدور «محامٍ» (من الـ Seeder) مربوطاً بموظف. */
    private function seededLawyer(): array
    {
        $this->seed(RbacSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('lawyer');
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        return [$user, $employee];
    }

    public function test_seeded_lawyer_reaches_dashboard_and_own_cases(): void
    {
        [$user, $employee] = $this->seededLawyer();

        $mine = LegalCase::factory()->create(['internal_number' => 'LP-1', 'responsible_lawyer_id' => $employee->id]);
        CaseAssignment::create(['case_id' => $mine->id, 'employee_id' => $employee->id, 'role' => 'lead']);
        LegalCase::factory()->create(['internal_number' => 'OTHER-1']); // قضية غير مسندة

        // اللوحة (تجميع ذاتي) — تعمل بصلاحية cases.view_own + الربط بموظف.
        $this->actingAsToken($user)->getJson('/api/me/legal-summary')
            ->assertOk()
            ->assertJsonPath('data.cases.total', 1);

        // القضايا — يرى المسندة فقط (عزل خادمي).
        $this->actingAsToken($user)->getJson('/api/cases')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.internal_number', 'LP-1');
    }

    public function test_seeded_lawyer_can_open_case_file_tabs(): void
    {
        [$user, $employee] = $this->seededLawyer();
        $case = LegalCase::factory()->create(['responsible_lawyer_id' => $employee->id]);
        CaseAssignment::create(['case_id' => $case->id, 'employee_id' => $employee->id, 'role' => 'lead']);

        // تبويبات ترث عزل القضية (view_own).
        $this->actingAsToken($user)->getJson("/api/cases/{$case->id}")->assertOk();
        $this->actingAsToken($user)->getJson("/api/cases/{$case->id}/parties")->assertOk();
        $this->actingAsToken($user)->getJson("/api/cases/{$case->id}/hearings")->assertOk();
        $this->actingAsToken($user)->getJson("/api/cases/{$case->id}/timeline")->assertOk();
        $this->actingAsToken($user)->getJson("/api/cases/{$case->id}/documents")->assertOk();
        // تبويبات بصلاحيات مستقلة منحها الدور.
        $this->actingAsToken($user)->getJson("/api/cases/{$case->id}/archive-locations")->assertOk();
        $this->actingAsToken($user)->getJson('/api/tasks?case_id='.$case->id)->assertOk();
        // الإنجاز اليومي (عرض).
        $this->actingAsToken($user)->getJson('/api/me/worklog')->assertOk();
    }

    public function test_seeded_lawyer_is_blocked_from_unassigned_case(): void
    {
        [$user] = $this->seededLawyer();
        $foreign = LegalCase::factory()->create();

        $this->actingAsToken($user)->getJson("/api/cases/{$foreign->id}")
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'FORBIDDEN');
    }
}
