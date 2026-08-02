<?php

namespace Tests\Feature\Hr;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * الحقول المالية/البنكية تُخفى إلا لمن يملك employees.salary.view (docs/05 §4 — Field-Level).
 */
class EmployeeFieldProtectionTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_salary_hidden_without_permission(): void
    {
        $viewer = $this->userWithPermissions(['employees.view']);
        $employee = Employee::factory()->create(['basic_salary' => 12000, 'bank_account' => 'SA123']);

        $res = $this->actingAsToken($viewer)->getJson("/api/employees/{$employee->id}")->assertOk();

        $this->assertArrayNotHasKey('basic_salary', $res->json('data'));
        $this->assertArrayNotHasKey('bank_account', $res->json('data'));
    }

    public function test_salary_visible_with_permission(): void
    {
        $viewer = $this->userWithPermissions(['employees.view', 'employees.salary.view']);
        $employee = Employee::factory()->create(['basic_salary' => 12000]);

        $res = $this->actingAsToken($viewer)->getJson("/api/employees/{$employee->id}")->assertOk();

        $this->assertEquals(12000, $res->json('data.basic_salary'));
    }

    /**
     * SEC-9: من يملك employees.create لكن لا يملك employees.salary.view لا يستطيع
     * ضبط الحقول المالية/البنكية عند الإنشاء — تُسقَط بصمت (كما تُخفى في القراءة).
     */
    private function orgUnits(): array
    {
        $branch = Branch::create(['name' => 'الرئيسي', 'code' => 'HQ']);
        $department = Department::create(['branch_id' => $branch->id, 'name' => 'القضايا']);

        return [$branch->id, $department->id];
    }

    public function test_salary_cannot_be_set_without_permission_on_create(): void
    {
        [$branchId, $departmentId] = $this->orgUnits();
        $creator = $this->userWithPermissions(['employees.create']);

        $res = $this->actingAsToken($creator)->postJson('/api/employees', [
            'branch_id' => $branchId, 'department_id' => $departmentId,
            'full_name_ar' => 'موظف اختبار', 'employee_no' => 'E-9001', 'national_id' => '1234509876',
            'basic_salary' => 15000, 'bank_account' => 'SA-SECRET',
        ])->assertCreated();

        $id = $res->json('data.id');
        // القيمة المُرسَلة (15000 / SA-SECRET) أُسقِطت — يبقى الافتراضي (0) وبنك فارغ.
        $this->assertDatabaseHas('employees', ['id' => $id, 'basic_salary' => 0, 'bank_account' => null]);
    }

    /** SEC-9: من يملك employees.salary.view يستطيع ضبط الراتب عند الإنشاء (سلوك مشروع). */
    public function test_salary_can_be_set_with_permission_on_create(): void
    {
        [$branchId, $departmentId] = $this->orgUnits();
        $creator = $this->userWithPermissions(['employees.create', 'employees.salary.view']);

        $res = $this->actingAsToken($creator)->postJson('/api/employees', [
            'branch_id' => $branchId, 'department_id' => $departmentId,
            'full_name_ar' => 'موظف براتب', 'employee_no' => 'E-9002', 'national_id' => '1234500001',
            'basic_salary' => 15000,
        ])->assertCreated();

        $this->assertDatabaseHas('employees', ['id' => $res->json('data.id'), 'basic_salary' => 15000]);
    }

    /** SEC-9: التحديث كذلك — بلا صلاحية الراتب لا يُغيَّر basic_salary القائم. */
    public function test_salary_cannot_be_changed_without_permission_on_update(): void
    {
        $editor = $this->userWithPermissions(['employees.update']);
        $employee = Employee::factory()->create(['basic_salary' => 8000]);

        $this->actingAsToken($editor)->putJson("/api/employees/{$employee->id}", [
            'basic_salary' => 99999,
        ])->assertOk();

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'basic_salary' => 8000]);
    }
}
