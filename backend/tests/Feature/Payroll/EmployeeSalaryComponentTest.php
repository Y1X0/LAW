<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\EmployeeSalaryComponent;
use Modules\Payroll\Models\SalaryComponent;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class EmployeeSalaryComponentTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function makeComponent(string $code = 'housing', string $type = 'allowance'): SalaryComponent
    {
        return SalaryComponent::create(['name' => $code, 'code' => $code, 'type' => $type, 'value_type' => 'fixed']);
    }

    public function test_can_assign_component_to_employee(): void
    {
        $admin = $this->userWithPermissions(['payroll.create']);
        $employee = Employee::factory()->create();
        $housing = $this->makeComponent();

        $this->actingAsToken($admin)->postJson("/api/employees/{$employee->id}/salary-components", [
            'salary_component_id' => $housing->id, 'value' => 100, 'effective_from' => '2026-01-01',
        ])->assertCreated()->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('employee_salary_components', [
            'employee_id' => $employee->id, 'salary_component_id' => $housing->id, 'is_active' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employee_component_assigned']);
    }

    public function test_multiple_distinct_components_coexist(): void
    {
        $admin = $this->userWithPermissions(['payroll.create']);
        $employee = Employee::factory()->create();
        $housing = $this->makeComponent('housing');
        $transport = $this->makeComponent('transport');

        foreach ([[$housing, 100], [$transport, 50]] as [$c, $v]) {
            $this->actingAsToken($admin)->postJson("/api/employees/{$employee->id}/salary-components", [
                'salary_component_id' => $c->id, 'value' => $v, 'effective_from' => '2026-01-01',
            ])->assertCreated();
        }

        // بدلان مختلفان يبقيان نشطين معاً.
        $this->assertSame(2, EmployeeSalaryComponent::where('employee_id', $employee->id)->where('is_active', true)->count());
    }

    public function test_reassigning_same_component_archives_previous(): void
    {
        $admin = $this->userWithPermissions(['payroll.create']);
        $employee = Employee::factory()->create();
        $housing = $this->makeComponent();

        $this->actingAsToken($admin)->postJson("/api/employees/{$employee->id}/salary-components", [
            'salary_component_id' => $housing->id, 'value' => 100, 'effective_from' => '2026-01-01',
        ])->assertCreated();
        $this->actingAsToken($admin)->postJson("/api/employees/{$employee->id}/salary-components", [
            'salary_component_id' => $housing->id, 'value' => 150, 'effective_from' => '2026-02-01',
        ])->assertCreated();

        // التاريخ محفوظ: إسنادان، النشط واحد بقيمة 150، والقديم أُرشِف بنهاية فعالية.
        $all = EmployeeSalaryComponent::where('employee_id', $employee->id)->where('salary_component_id', $housing->id)->get();
        $this->assertCount(2, $all);
        $active = $all->firstWhere('is_active', true);
        $this->assertSame('150.00', $active->value);
        $old = $all->firstWhere('value', '100.00');
        $this->assertFalse($old->is_active);
        $this->assertSame('2026-01-31', $old->effective_to->toDateString());
    }

    public function test_can_deactivate_assignment(): void
    {
        $admin = $this->userWithPermissions(['payroll.create']);
        $employee = Employee::factory()->create();
        $housing = $this->makeComponent();
        $assignment = EmployeeSalaryComponent::create([
            'employee_id' => $employee->id, 'salary_component_id' => $housing->id,
            'value' => 100, 'effective_from' => '2026-01-01', 'is_active' => true,
        ]);

        $this->actingAsToken($admin)->deleteJson("/api/employee-salary-components/{$assignment->id}")
            ->assertOk();

        $fresh = $assignment->fresh();
        $this->assertFalse($fresh->is_active);
        $this->assertNotNull($fresh->effective_to);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employee_component_deactivated']);
    }

    public function test_assign_requires_permission(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $employee = Employee::factory()->create();
        $housing = $this->makeComponent();

        $this->actingAsToken($viewer)->postJson("/api/employees/{$employee->id}/salary-components", [
            'salary_component_id' => $housing->id, 'value' => 100,
        ])->assertStatus(403);
    }

    public function test_can_list_active_assignments(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $employee = Employee::factory()->create();
        $housing = $this->makeComponent();
        EmployeeSalaryComponent::create([
            'employee_id' => $employee->id, 'salary_component_id' => $housing->id,
            'value' => 100, 'effective_from' => '2026-01-01', 'is_active' => true,
        ]);

        $res = $this->actingAsToken($viewer)->getJson("/api/employees/{$employee->id}/salary-components?active=1")
            ->assertOk();
        $this->assertSame('100.00', $res->json('data.0.value'));
    }
}
