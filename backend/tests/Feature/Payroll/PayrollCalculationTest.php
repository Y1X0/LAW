<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\EmployeeSalaryComponent;
use Modules\Payroll\Models\EmployeeSalaryProfile;
use Modules\Payroll\Models\PayrollAttendanceSummary;
use Modules\Payroll\Models\PayrollItem;
use Modules\Payroll\Models\PayrollLeaveSummary;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\SalaryComponent;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class PayrollCalculationTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function employeeWithBasic(float $basic = 3000): Employee
    {
        $employee = Employee::factory()->create();
        EmployeeSalaryProfile::create(['employee_id' => $employee->id, 'basic_salary' => $basic, 'currency' => 'SAR', 'effective_from' => '2026-01-01', 'is_active' => true]);

        return $employee;
    }

    private function assign(Employee $e, string $type, string $valueType, float $value, string $code): void
    {
        $c = SalaryComponent::create(['name' => $code, 'code' => $code, 'type' => $type, 'value_type' => $valueType]);
        EmployeeSalaryComponent::create(['employee_id' => $e->id, 'salary_component_id' => $c->id, 'value' => $value, 'effective_from' => '2026-01-01', 'is_active' => true]);
    }

    private function draftRun(): PayrollRun
    {
        $period = PayrollPeriod::create(['year' => 2026, 'month' => 6, 'status' => 'draft']);

        return PayrollRun::create(['payroll_period_id' => $period->id, 'status' => 'draft']);
    }

    public function test_full_calculation_scenario(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        $employee = $this->employeeWithBasic(3000); // يومي=100، ساعة=12.5، دقيقة=0.2083
        $this->assign($employee, 'allowance', 'fixed', 500, 'housing');
        $this->assign($employee, 'allowance', 'percentage', 10, 'transport'); // 10% × 3000 = 300
        $this->assign($employee, 'deduction', 'fixed', 200, 'loan');

        $run = $this->draftRun();
        PayrollAttendanceSummary::create([
            'payroll_run_id' => $run->id, 'employee_id' => $employee->id,
            'absent_days' => 1, 'late_minutes' => 60, 'overtime_minutes' => 120, // إضافي ساعتان
        ]);
        PayrollLeaveSummary::create([
            'payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'unpaid_leave_days' => 2,
        ]);

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/calculate")
            ->assertOk()->assertJsonPath('data.employees', 1);

        $item = PayrollItem::where('payroll_run_id', $run->id)->firstOrFail();
        // بدلات: 500 + 300 + إضافي(12.5×1.5×2=37.5) = 837.5
        $this->assertEquals(837.5, (float) $item->allowances_total);
        // خصومات: قرض 200 + غياب 100 + تأخير 12.5 + إجازة بدون راتب 200 = 512.5
        $this->assertEquals(512.5, (float) $item->deductions_total);
        $this->assertEquals(3837.5, (float) $item->gross_amount); // 3000 + 837.5
        $this->assertEquals(3325.0, (float) $item->net_amount);   // 3837.5 - 512.5
        $this->assertDatabaseHas('audit_logs', ['action' => 'payroll_calculated']);
    }

    public function test_basic_only_gives_net_equal_to_basic(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        $employee = $this->employeeWithBasic(4000);
        $run = $this->draftRun();

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/calculate")->assertOk();

        $item = PayrollItem::where('payroll_run_id', $run->id)->firstOrFail();
        $this->assertEquals(4000.0, (float) $item->gross_amount);
        $this->assertEquals(0.0, (float) $item->deductions_total);
        $this->assertEquals(4000.0, (float) $item->net_amount);
    }

    public function test_breakdown_is_stored(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        $employee = $this->employeeWithBasic(3000);
        $this->assign($employee, 'allowance', 'fixed', 500, 'housing');
        $run = $this->draftRun();

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/calculate")->assertOk();

        $item = PayrollItem::where('payroll_run_id', $run->id)->firstOrFail();
        $this->assertEquals(100, $item->breakdown['rates']['daily']);
        $codes = array_column($item->breakdown['earnings'], 'code');
        $this->assertContains('basic', $codes);
        $this->assertContains('allowance:housing', $codes);
    }

    public function test_cannot_recalculate_approved_run(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        $this->employeeWithBasic(3000);
        $period = PayrollPeriod::create(['year' => 2026, 'month' => 6, 'status' => 'approved']);
        $run = PayrollRun::create(['payroll_period_id' => $period->id, 'status' => 'approved']);

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/calculate")
            ->assertStatus(422);
        $this->assertDatabaseCount('payroll_items', 0);
    }

    public function test_recalculation_is_idempotent(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        $this->employeeWithBasic(3000);
        $run = $this->draftRun();

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/calculate")->assertOk();
        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/calculate")->assertOk();

        $this->assertDatabaseCount('payroll_items', 1);
    }

    public function test_calculate_requires_permission(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $this->employeeWithBasic(3000);
        $run = $this->draftRun();

        $this->actingAsToken($viewer)->postJson("/api/payroll-runs/{$run->id}/calculate")
            ->assertStatus(403);
    }

    public function test_can_list_run_items(): void
    {
        $actor = $this->userWithPermissions(['payroll.create', 'payroll.view']);
        $this->employeeWithBasic(3000);
        $run = $this->draftRun();
        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/calculate")->assertOk();

        $this->actingAsToken($actor)->getJson("/api/payroll-runs/{$run->id}/items")
            ->assertOk()->assertJsonPath('data.0.payroll_run_id', $run->id);
    }
}
