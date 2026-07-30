<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\PayrollItem;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Models\PayrollRun;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class PayslipTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** @return array{0:PayrollRun,1:PayrollItem,2:Employee} */
    private function itemFor(string $runStatus = 'approved'): array
    {
        $employee = Employee::factory()->create(['full_name_ar' => 'أحمد', 'employee_no' => 'EMP-001']);
        $period = PayrollPeriod::create(['year' => 2027, 'month' => 1, 'status' => $runStatus]);
        $run = PayrollRun::create(['payroll_period_id' => $period->id, 'status' => $runStatus]);
        $item = PayrollItem::create([
            'payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'currency' => 'SAR',
            'basic_salary' => 3000, 'allowances_total' => 500, 'deductions_total' => 100,
            'gross_amount' => 3500, 'net_amount' => 3400,
            'breakdown' => [
                'earnings' => [['code' => 'basic', 'amount' => 3000], ['code' => 'allowance:housing', 'amount' => 500]],
                'deductions' => [['code' => 'absence', 'amount' => 100]],
            ],
        ]);

        return [$run, $item, $employee];
    }

    public function test_payslip_json_reflects_frozen_result(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        [, $item] = $this->itemFor('approved');

        $res = $this->actingAsToken($viewer)->getJson("/api/payroll-items/{$item->id}/payslip")->assertOk();

        $this->assertSame('أحمد', $res->json('data.employee.name'));
        $this->assertSame('EMP-001', $res->json('data.employee.employee_number'));
        $this->assertSame(2027, $res->json('data.period.year'));
        $this->assertEquals(3500, $res->json('data.gross'));
        $this->assertEquals(100, $res->json('data.deductions_total'));
        $this->assertEquals(3400, $res->json('data.net'));
        $this->assertSame('approved', $res->json('data.status'));
        // التسميات المقروءة من الأكواد
        $this->assertSame('Basic Salary', $res->json('data.earnings.0.name'));
        $this->assertSame('Housing', $res->json('data.earnings.1.name'));
        $this->assertSame('Absence', $res->json('data.deductions.0.name'));
    }

    public function test_payslip_list_for_run(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        [$run] = $this->itemFor('approved');

        $res = $this->actingAsToken($viewer)->getJson("/api/payroll-runs/{$run->id}/payslips")->assertOk();
        $this->assertEquals(3400, $res->json('data.0.net'));
    }

    public function test_payslip_html_is_printable_document(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        [, $item] = $this->itemFor('approved');

        $res = $this->actingAsToken($viewer)->get("/api/payroll-items/{$item->id}/payslip/html")
            ->assertOk();

        $this->assertStringContainsString('text/html', $res->headers->get('Content-Type'));
        $res->assertSee('أحمد', false);
        $res->assertSee('3,400.00', false); // صافي الراتب
        $this->assertDatabaseHas('audit_logs', ['action' => 'payslip_exported']);
    }

    public function test_payslip_requires_permission(): void
    {
        $noPerm = $this->userWithPermissions([]);
        [, $item] = $this->itemFor('approved');

        $this->actingAsToken($noPerm)->getJson("/api/payroll-items/{$item->id}/payslip")
            ->assertStatus(403);
    }

    public function test_payslip_routes_require_authentication(): void
    {
        [, $item] = $this->itemFor('approved');
        $this->getJson("/api/payroll-items/{$item->id}/payslip")->assertStatus(401);
    }
}
