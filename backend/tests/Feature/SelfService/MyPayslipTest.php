<?php

namespace Tests\Feature\SelfService;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\PayrollItem;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Models\PayrollRun;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class MyPayslipTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** @return array{0:User, 1:Employee} */
    private function linkedUser(array $permissions = ['payslip.view_own']): array
    {
        $user = $this->userWithPermissions($permissions);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'full_name_ar' => 'سارة', 'employee_no' => 'EMP-9']);

        return [$user, $employee];
    }

    private function payslipItem(Employee $employee, int $year, int $month, string $status, float $net = 3400): PayrollItem
    {
        $period = PayrollPeriod::create(['year' => $year, 'month' => $month, 'status' => 'draft']);
        $run = PayrollRun::create(['payroll_period_id' => $period->id, 'status' => $status]);

        return PayrollItem::create([
            'payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'currency' => 'SAR',
            'basic_salary' => 3000, 'allowances_total' => 500, 'deductions_total' => 100,
            'gross_amount' => 3500, 'net_amount' => $net,
            'breakdown' => ['earnings' => [['code' => 'basic', 'amount' => 3000]], 'deductions' => []],
        ]);
    }

    public function test_lists_only_my_finalized_payslips(): void
    {
        [$user, $employee] = $this->linkedUser();
        $this->payslipItem($employee, 2027, 1, 'approved', 3400);
        $this->payslipItem($employee, 2027, 2, 'draft', 9999); // مسوّدة — لا تظهر
        // كشف موظف آخر — لا يظهر
        $this->payslipItem(Employee::factory()->create(), 2027, 3, 'approved', 12345);

        $res = $this->actingAsToken($user)->getJson('/api/me/payslips')->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertEquals(3400, $res->json('data.0.net'));
    }

    public function test_show_my_payslip(): void
    {
        [$user, $employee] = $this->linkedUser();
        $item = $this->payslipItem($employee, 2027, 1, 'approved', 3400);

        $res = $this->actingAsToken($user)->getJson("/api/me/payslips/{$item->id}")->assertOk();

        $this->assertSame('سارة', $res->json('data.employee.name'));
        $this->assertEquals(3400, $res->json('data.net'));
    }

    public function test_cannot_view_another_employees_payslip(): void
    {
        [$user] = $this->linkedUser();
        $otherItem = $this->payslipItem(Employee::factory()->create(), 2027, 1, 'approved');

        $this->actingAsToken($user)->getJson("/api/me/payslips/{$otherItem->id}")->assertStatus(403);
    }

    public function test_cannot_view_draft_payslip(): void
    {
        [$user, $employee] = $this->linkedUser();
        $draft = $this->payslipItem($employee, 2027, 1, 'draft');

        $this->actingAsToken($user)->getJson("/api/me/payslips/{$draft->id}")->assertStatus(403);
    }

    public function test_html_export_is_printable_and_audited(): void
    {
        [$user, $employee] = $this->linkedUser();
        $item = $this->payslipItem($employee, 2027, 1, 'locked', 3400);

        $res = $this->actingAsToken($user)->get("/api/me/payslips/{$item->id}/html")->assertOk();

        $this->assertStringContainsString('text/html', $res->headers->get('Content-Type'));
        $res->assertSee('سارة', false);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payslip_self_exported', 'auditable_id' => $item->id,
        ]);
    }

    public function test_requires_permission(): void
    {
        [$user, $employee] = $this->linkedUser([]); // موظف مرتبط بلا صلاحية
        $item = $this->payslipItem($employee, 2027, 1, 'approved');

        $this->actingAsToken($user)->getJson('/api/me/payslips')->assertStatus(403);
        $this->actingAsToken($user)->getJson("/api/me/payslips/{$item->id}")->assertStatus(403);
    }

    public function test_forbidden_for_unlinked_user(): void
    {
        $user = $this->userWithPermissions(['payslip.view_own']); // بلا موظف مرتبط

        $this->actingAsToken($user)->getJson('/api/me/payslips')
            ->assertStatus(403)->assertJsonPath('errors.code', 'NO_LINKED_EMPLOYEE');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/me/payslips')->assertStatus(401);
    }
}
