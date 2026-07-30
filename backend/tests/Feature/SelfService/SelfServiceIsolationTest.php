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

/**
 * مراجعة تكاملية لعزل الخدمة الذاتية (Epic 9 / #48 + #49):
 * عزل الصلاحيات المستقلة + عزل بيانات الموظفين طرفاً لطرف.
 */
class SelfServiceIsolationTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function linked(User $user): Employee
    {
        return Employee::factory()->create(['user_id' => $user->id]);
    }

    private function finalizedItem(Employee $employee, int $month, float $net): PayrollItem
    {
        $period = PayrollPeriod::create(['year' => 2027, 'month' => $month, 'status' => 'draft']);
        $run = PayrollRun::create(['payroll_period_id' => $period->id, 'status' => 'approved']);

        return PayrollItem::create([
            'payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'currency' => 'SAR',
            'basic_salary' => $net, 'allowances_total' => 0, 'deductions_total' => 0,
            'gross_amount' => $net, 'net_amount' => $net, 'breakdown' => ['earnings' => [], 'deductions' => []],
        ]);
    }

    public function test_dashboard_permission_does_not_grant_payslips(): void
    {
        $user = $this->userWithPermissions(['dashboard.view_own']);
        $this->linked($user);

        $this->actingAsToken($user)->getJson('/api/me/dashboard')->assertOk();
        $this->actingAsToken($user)->getJson('/api/me/payslips')->assertStatus(403);
    }

    public function test_payslip_permission_does_not_grant_dashboard(): void
    {
        $user = $this->userWithPermissions(['payslip.view_own']);
        $this->linked($user);

        $this->actingAsToken($user)->getJson('/api/me/payslips')->assertOk();
        $this->actingAsToken($user)->getJson('/api/me/dashboard')->assertStatus(403);
    }

    public function test_two_employees_see_only_their_own_data(): void
    {
        $userA = $this->userWithPermissions(['dashboard.view_own', 'payslip.view_own']);
        $userB = $this->userWithPermissions(['dashboard.view_own', 'payslip.view_own']);
        $empA = $this->linked($userA);
        $empB = $this->linked($userB);

        $itemA = $this->finalizedItem($empA, 1, 1000);
        $itemB = $this->finalizedItem($empB, 1, 2000);

        // كل موظف يرى راتبه فقط في لوحته.
        $this->assertEquals(1000, $this->actingAsToken($userA)->getJson('/api/me/dashboard')->json('data.last_payslip.net'));
        $this->assertEquals(2000, $this->actingAsToken($userB)->getJson('/api/me/dashboard')->json('data.last_payslip.net'));

        // A لا يستطيع فتح كشف B والعكس.
        $this->actingAsToken($userA)->getJson("/api/me/payslips/{$itemB->id}")->assertStatus(403);
        $this->actingAsToken($userB)->getJson("/api/me/payslips/{$itemA->id}")->assertStatus(403);

        // قائمة كل موظف تحوي كشفه فقط.
        $this->assertCount(1, $this->actingAsToken($userA)->getJson('/api/me/payslips')->json('data'));
        $this->assertEquals(1000, $this->actingAsToken($userA)->getJson('/api/me/payslips')->json('data.0.net'));
    }
}
