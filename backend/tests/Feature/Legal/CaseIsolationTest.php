<?php

namespace Tests\Feature\Legal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\CaseAssignment;
use Modules\Legal\Models\LegalCase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * عزل رؤية القضايا (LC-2): المحامي المسند فقط (view_own) مقابل الإدارة (view_all).
 * لا تسريب بين المحامين — لا في القائمة ولا في العرض المباشر.
 */
class CaseIsolationTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** محامٍ = مستخدم بصلاحية view_own مرتبط بموظف، مع قضية مسندة إليه. */
    private function lawyerWithCase(string $internal): array
    {
        $user = $this->userWithPermissions(['cases.view_own']);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $case = LegalCase::factory()->create(['internal_number' => $internal, 'responsible_lawyer_id' => $employee->id]);
        CaseAssignment::create(['case_id' => $case->id, 'employee_id' => $employee->id, 'role' => 'lead']);

        return [$user, $employee, $case];
    }

    public function test_lawyer_sees_only_assigned_cases_in_list(): void
    {
        [$userA, , $caseA] = $this->lawyerWithCase('A-1');
        $this->lawyerWithCase('B-1'); // قضية المحامي B

        $this->actingAsToken($userA)->getJson('/api/cases')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $caseA->id);
    }

    public function test_lawyer_cannot_view_another_lawyers_case(): void
    {
        [$userA, , $caseA] = $this->lawyerWithCase('A-2');
        [, , $caseB] = $this->lawyerWithCase('B-2');

        // قضيته → 200 ؛ قضية غيره → 403 مع الغلاف الموحّد.
        $this->actingAsToken($userA)->getJson("/api/cases/{$caseA->id}")->assertOk();
        $this->actingAsToken($userA)->getJson("/api/cases/{$caseB->id}")
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'FORBIDDEN');
    }

    public function test_admin_with_view_all_sees_every_case(): void
    {
        $this->lawyerWithCase('A-3');
        [, , $caseB] = $this->lawyerWithCase('B-3');

        $admin = $this->userWithPermissions(['cases.view_all']);
        $this->actingAsToken($admin)->getJson('/api/cases')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        // الإدارة ترى أي قضية مباشرةً.
        $this->actingAsToken($admin)->getJson("/api/cases/{$caseB->id}")->assertOk();
    }

    public function test_view_own_without_linked_employee_is_forbidden(): void
    {
        $orphan = $this->userWithPermissions(['cases.view_own']); // بلا موظف مرتبط
        LegalCase::factory()->create();

        $this->actingAsToken($orphan)->getJson('/api/cases')
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'NO_LINKED_EMPLOYEE');
    }

    public function test_assigning_a_lawyer_grants_visibility(): void
    {
        // محامٍ بلا قضايا في البداية.
        $user = $this->userWithPermissions(['cases.view_own']);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $case = LegalCase::factory()->create(['internal_number' => 'C-1']);

        $this->actingAsToken($user)->getJson("/api/cases/{$case->id}")->assertStatus(403);

        // بعد الإسناد بواسطة الإدارة → يرى القضية.
        $assigner = $this->userWithPermissions(['cases.assign']);
        $this->actingAsToken($assigner)
            ->postJson("/api/cases/{$case->id}/assign", ['employee_id' => $employee->id, 'role' => 'support'])
            ->assertCreated();

        $this->actingAsToken($user)->getJson("/api/cases/{$case->id}")->assertOk();
    }

    public function test_user_model_relation_resolves_employee(): void
    {
        $user = $this->userWithPermissions(['cases.view_own']);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $this->assertSame($employee->id, User::find($user->id)->employee->id);
    }
}
