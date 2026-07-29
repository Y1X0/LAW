<?php

namespace Tests\Feature\Hr;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeContract;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class EmployeeContractTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_can_add_and_list_contracts_for_employee(): void
    {
        $hr = $this->userWithPermissions(['employees.update', 'employees.view']);
        $employee = Employee::factory()->create();

        $this->actingAsToken($hr)->postJson("/api/employees/{$employee->id}/contracts", [
            'contract_type' => 'permanent',
            'start_date' => '2026-01-01',
            'basic_salary' => 15000,
        ])->assertCreated();

        $this->assertDatabaseHas('employee_contracts', ['employee_id' => $employee->id, 'contract_type' => 'permanent']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employee_contract_added']);

        $this->actingAsToken($hr)->getJson("/api/employees/{$employee->id}/contracts")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_contract_dates_are_validated(): void
    {
        $hr = $this->userWithPermissions(['employees.update']);
        $employee = Employee::factory()->create();

        $this->actingAsToken($hr)->postJson("/api/employees/{$employee->id}/contracts", [
            'contract_type' => 'temporary',
            'start_date' => '2026-06-01',
            'end_date' => '2026-01-01', // قبل البداية
        ])->assertStatus(422);
    }

    public function test_can_update_contract_status(): void
    {
        $hr = $this->userWithPermissions(['employees.update']);
        $employee = Employee::factory()->create();
        $contract = EmployeeContract::create([
            'employee_id' => $employee->id, 'contract_type' => 'permanent',
            'start_date' => '2026-01-01', 'status' => 'active',
        ]);

        $this->actingAsToken($hr)->putJson("/api/employees/{$employee->id}/contracts/{$contract->id}", [
            'status' => 'terminated',
        ])->assertOk()->assertJsonPath('data.status', 'terminated');
    }

    public function test_contract_of_other_employee_returns_404(): void
    {
        $hr = $this->userWithPermissions(['employees.update']);
        $employeeA = Employee::factory()->create();
        $employeeB = Employee::factory()->create();
        $contract = EmployeeContract::create([
            'employee_id' => $employeeA->id, 'contract_type' => 'permanent', 'start_date' => '2026-01-01',
        ]);

        // تحديث عقد الموظف A عبر مسار الموظف B → 404 (ارتباط صحيح)
        $this->actingAsToken($hr)->putJson("/api/employees/{$employeeB->id}/contracts/{$contract->id}", [
            'status' => 'terminated',
        ])->assertStatus(404);
    }

    public function test_contract_write_requires_permission(): void
    {
        $viewer = $this->userWithPermissions(['employees.view']);
        $employee = Employee::factory()->create();

        $this->actingAsToken($viewer)->postJson("/api/employees/{$employee->id}/contracts", [
            'contract_type' => 'permanent', 'start_date' => '2026-01-01',
        ])->assertStatus(403);
    }
}
