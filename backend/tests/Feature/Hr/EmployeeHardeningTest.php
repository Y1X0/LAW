<?php

namespace Tests\Feature\Hr;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * تحصينات دورة حياة الموظف (مراجعة Lead Architect): توليد الرقم الوظيفي، البحث بالبريد،
 * حقول إنهاء الخدمة، ومنع الحلقات الإدارية وتعيين المدير لنفسه.
 */
class EmployeeHardeningTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function org(): array
    {
        $b = Branch::create(['name' => 'الرئيسي', 'code' => 'HQ']);
        $d = Department::create(['branch_id' => $b->id, 'name' => 'الإدارة']);

        return [$b, $d];
    }

    public function test_employee_no_is_auto_generated_when_omitted(): void
    {
        [$b, $d] = $this->org();
        $actor = $this->userWithPermissions(['employees.create']);

        $this->actingAsToken($actor)->postJson('/api/employees', [
            'branch_id' => $b->id, 'department_id' => $d->id,
            'full_name_ar' => 'بلا رقم', 'national_id' => '111',
        ])->assertStatus(201)->assertJsonPath('data.employee_no', 'EMP-1001');

        // الرقم الثاني يتسلسل.
        $this->actingAsToken($actor)->postJson('/api/employees', [
            'branch_id' => $b->id, 'department_id' => $d->id,
            'full_name_ar' => 'ثانٍ', 'national_id' => '112',
        ])->assertStatus(201)->assertJsonPath('data.employee_no', 'EMP-1002');
    }

    public function test_manual_employee_no_is_respected(): void
    {
        [$b, $d] = $this->org();
        $actor = $this->userWithPermissions(['employees.create']);

        $this->actingAsToken($actor)->postJson('/api/employees', [
            'branch_id' => $b->id, 'department_id' => $d->id, 'employee_no' => 'HR-500',
            'full_name_ar' => 'يدوي', 'national_id' => '113',
        ])->assertStatus(201)->assertJsonPath('data.employee_no', 'HR-500');
    }

    public function test_search_matches_email(): void
    {
        [$b, $d] = $this->org();
        Employee::factory()->create(['branch_id' => $b->id, 'department_id' => $d->id, 'email' => 'sara.hr@firm.test']);
        $actor = $this->userWithPermissions(['employees.view']);

        $this->actingAsToken($actor)->getJson('/api/employees?search=sara.hr')
            ->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_termination_fields_are_saved(): void
    {
        [$b, $d] = $this->org();
        $actor = $this->userWithPermissions(['employees.create']);

        $this->actingAsToken($actor)->postJson('/api/employees', [
            'branch_id' => $b->id, 'department_id' => $d->id, 'employee_no' => 'T-1',
            'full_name_ar' => 'منتهٍ', 'national_id' => '114',
            'status' => 'terminated', 'termination_date' => '2026-01-15', 'termination_reason' => 'استقالة',
        ])->assertStatus(201);

        $this->assertDatabaseHas('employees', ['employee_no' => 'T-1', 'termination_reason' => 'استقالة']);
    }

    public function test_manager_cannot_be_self(): void
    {
        [$b, $d] = $this->org();
        $actor = $this->userWithPermissions(['employees.update']);
        $emp = Employee::factory()->create(['branch_id' => $b->id, 'department_id' => $d->id]);

        $this->actingAsToken($actor)->putJson("/api/employees/{$emp->id}", ['manager_id' => $emp->id])
            ->assertStatus(422);
    }

    public function test_manager_cycle_is_rejected(): void
    {
        [$b, $d] = $this->org();
        $actor = $this->userWithPermissions(['employees.update']);
        $a = Employee::factory()->create(['branch_id' => $b->id, 'department_id' => $d->id]);
        $bEmp = Employee::factory()->create(['branch_id' => $b->id, 'department_id' => $d->id, 'manager_id' => $a->id]);

        // A مدير B؛ محاولة جعل B مديراً لـ A ⇒ حلقة ⇒ رفض.
        $this->actingAsToken($actor)->putJson("/api/employees/{$a->id}", ['manager_id' => $bEmp->id])
            ->assertStatus(422)->assertJsonPath('errors.fields.manager_id.0', 'اختيار هذا المدير ينشئ حلقة إدارية غير صالحة.');
    }
}
