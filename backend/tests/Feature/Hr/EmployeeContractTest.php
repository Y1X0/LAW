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

    public function test_contract_list_hides_basic_salary_without_salary_permission(): void
    {
        $employee = Employee::factory()->create();
        EmployeeContract::create([
            'employee_id' => $employee->id, 'contract_type' => 'permanent',
            'start_date' => '2026-01-01', 'basic_salary' => 15000,
        ]);

        // employees.view دون employees.salary.view → الراتب مخفي (لا تسريب عبر العقود).
        $viewer = $this->userWithPermissions(['employees.view']);
        $row = $this->actingAsToken($viewer)->getJson("/api/employees/{$employee->id}/contracts")
            ->assertOk()->json('data.0');
        $this->assertArrayNotHasKey('basic_salary', $row);

        // مع employees.salary.view → الراتب ظاهر.
        $payroll = $this->userWithPermissions(['employees.view', 'employees.salary.view']);
        $row = $this->actingAsToken($payroll)->getJson("/api/employees/{$employee->id}/contracts")
            ->assertOk()->json('data.0');
        $this->assertArrayHasKey('basic_salary', $row);
        $this->assertEquals(15000, $row['basic_salary']);
    }

    /**
     * Sec F1: ردّ store لا يسرّب basic_salary لمن لا يملك salary.view (كان يعيد النموذج خامّاً)،
     * وSec F3: لا يُضبط الراتب على العقد بلا الصلاحية (اتساقاً مع SEC-9).
     */
    public function test_contract_store_neither_reveals_nor_sets_salary_without_permission(): void
    {
        $hr = $this->userWithPermissions(['employees.update']); // بلا salary.view
        $employee = Employee::factory()->create();

        $res = $this->actingAsToken($hr)->postJson("/api/employees/{$employee->id}/contracts", [
            'contract_type' => 'permanent', 'start_date' => '2026-01-01', 'basic_salary' => 15000,
        ])->assertCreated();

        $this->assertArrayNotHasKey('basic_salary', $res->json('data'));           // لا تسريب في الردّ
        $this->assertDatabaseMissing('employee_contracts', [                        // لم يُضبط الراتب
            'id' => $res->json('data.id'), 'basic_salary' => 15000,
        ]);
    }

    /** Sec F1: ردّ update لا يسرّب basic_salary القائم لمن لا يملك salary.view. */
    public function test_contract_update_response_hides_salary_without_permission(): void
    {
        $editor = $this->userWithPermissions(['employees.update']); // بلا salary.view
        $employee = Employee::factory()->create();
        $contract = EmployeeContract::create([
            'employee_id' => $employee->id, 'contract_type' => 'permanent',
            'start_date' => '2026-01-01', 'basic_salary' => 15000, 'status' => 'active',
        ]);

        $res = $this->actingAsToken($editor)->putJson("/api/employees/{$employee->id}/contracts/{$contract->id}", [
            'status' => 'terminated',
        ])->assertOk();

        $this->assertArrayNotHasKey('basic_salary', $res->json('data'));
    }

    /** Sec F3: مع salary.view يُضبط الراتب على العقد ويظهر في الردّ. */
    public function test_contract_store_persists_and_shows_salary_with_permission(): void
    {
        $payroll = $this->userWithPermissions(['employees.update', 'employees.salary.view']);
        $employee = Employee::factory()->create();

        $res = $this->actingAsToken($payroll)->postJson("/api/employees/{$employee->id}/contracts", [
            'contract_type' => 'permanent', 'start_date' => '2026-01-01', 'basic_salary' => 15000,
        ])->assertCreated();

        $this->assertEquals(15000, $res->json('data.basic_salary'));
        $this->assertDatabaseHas('employee_contracts', ['id' => $res->json('data.id'), 'basic_salary' => 15000]);
    }
}
