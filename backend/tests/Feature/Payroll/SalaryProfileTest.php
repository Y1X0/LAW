<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\EmployeeSalaryProfile;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class SalaryProfileTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_can_set_active_salary_profile(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        $employee = Employee::factory()->create();

        $this->actingAsToken($actor)->postJson("/api/employees/{$employee->id}/salary-profiles", [
            'basic_salary' => 1000, 'payment_method' => 'bank', 'effective_from' => '2026-01-01',
        ])->assertCreated()->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('employee_salary_profiles', [
            'employee_id' => $employee->id, 'basic_salary' => 1000, 'is_active' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'salary_profile_set']);
    }

    public function test_new_profile_archives_previous_and_keeps_history(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        $employee = Employee::factory()->create();

        $this->actingAsToken($actor)->postJson("/api/employees/{$employee->id}/salary-profiles", [
            'basic_salary' => 1000, 'effective_from' => '2026-01-01',
        ])->assertCreated();

        $this->actingAsToken($actor)->postJson("/api/employees/{$employee->id}/salary-profiles", [
            'basic_salary' => 1200, 'effective_from' => '2026-02-01',
        ])->assertCreated();

        // التاريخ محفوظ: ملفان، النشط واحد فقط بقيمة 1200.
        $this->assertSame(2, EmployeeSalaryProfile::where('employee_id', $employee->id)->count());
        $active = EmployeeSalaryProfile::where('employee_id', $employee->id)->where('is_active', true)->get();
        $this->assertCount(1, $active);
        $this->assertSame('1200.00', $active->first()->basic_salary);

        // القديم أُرشِف بنهاية صلاحية قبل بداية الجديد.
        $old = EmployeeSalaryProfile::where('employee_id', $employee->id)->where('basic_salary', 1000)->first();
        $this->assertFalse($old->is_active);
        $this->assertSame('2026-01-31', $old->effective_to->toDateString());
    }

    public function test_set_profile_requires_permission(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $employee = Employee::factory()->create();

        $this->actingAsToken($viewer)->postJson("/api/employees/{$employee->id}/salary-profiles", [
            'basic_salary' => 1000,
        ])->assertStatus(403);
    }

    public function test_can_list_salary_history(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $employee = Employee::factory()->create();
        EmployeeSalaryProfile::create([
            'employee_id' => $employee->id, 'basic_salary' => 900, 'effective_from' => '2026-01-01', 'is_active' => true,
        ]);

        $res = $this->actingAsToken($viewer)->getJson("/api/employees/{$employee->id}/salary-profiles")->assertOk();
        $this->assertSame('900.00', $res->json('data.0.basic_salary'));
    }
}
