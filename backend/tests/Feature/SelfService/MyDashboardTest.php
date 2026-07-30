<?php

namespace Tests\Feature\SelfService;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\HR\Models\Employee;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveType;
use Modules\Payroll\Models\PayrollItem;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Models\PayrollRun;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class MyDashboardTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** ينشئ مستخدماً بصلاحيات + موظفاً مرتبطاً به. */
    private function linkedUser(array $permissions = ['dashboard.view_own']): array
    {
        $user = $this->userWithPermissions($permissions);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'job_title' => 'محامٍ']);

        return [$user, $employee];
    }

    private function finalizedPayslip(Employee $employee, int $year, int $month, string $status, float $net): void
    {
        $period = PayrollPeriod::create(['year' => $year, 'month' => $month, 'status' => 'draft']);
        $run = PayrollRun::create(['payroll_period_id' => $period->id, 'status' => $status]);
        PayrollItem::create([
            'payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'currency' => 'SAR',
            'basic_salary' => $net, 'allowances_total' => 0, 'deductions_total' => 0,
            'gross_amount' => $net, 'net_amount' => $net, 'breakdown' => ['earnings' => [], 'deductions' => []],
        ]);
    }

    public function test_dashboard_returns_personal_summary(): void
    {
        [$user, $employee] = $this->linkedUser();

        $res = $this->actingAsToken($user)->getJson('/api/me/dashboard')->assertOk();

        $this->assertSame($employee->full_name_ar, $res->json('data.profile.name'));
        $this->assertSame('محامٍ', $res->json('data.profile.job_title'));
        $this->assertSame($employee->employee_no, $res->json('data.profile.employee_number'));
    }

    public function test_dashboard_leave_balance_sums_current_year(): void
    {
        [$user, $employee] = $this->linkedUser();
        $type = LeaveType::create(['name' => 'سنوية', 'code' => 'annual', 'is_paid' => true, 'consumes_balance' => true, 'is_active' => true]);
        LeaveBalance::create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => now()->year, 'entitled_days' => 30, 'consumed_days' => 8]);

        $res = $this->actingAsToken($user)->getJson('/api/me/dashboard')->assertOk();

        $this->assertEquals(22, $res->json('data.leave_balance.total_remaining'));
        $this->assertSame('سنوية', $res->json('data.leave_balance.by_type.0.type'));
    }

    public function test_dashboard_attendance_today(): void
    {
        [$user, $employee] = $this->linkedUser();
        AttendanceRecord::create([
            'employee_id' => $employee->id, 'work_date' => today()->toDateString(),
            'status' => 'late', 'late_minutes' => 15, 'worked_minutes' => 420,
        ]);

        $res = $this->actingAsToken($user)->getJson('/api/me/dashboard')->assertOk();

        $this->assertSame('late', $res->json('data.attendance_today.status'));
        $this->assertEquals(15, $res->json('data.attendance_today.late_minutes'));
    }

    public function test_dashboard_attendance_today_null_when_absent_record(): void
    {
        [$user] = $this->linkedUser();

        $res = $this->actingAsToken($user)->getJson('/api/me/dashboard')->assertOk();
        $this->assertNull($res->json('data.attendance_today'));
    }

    public function test_last_payslip_shows_finalized_only(): void
    {
        [$user, $employee] = $this->linkedUser();
        // مسوّدة أحدث (يجب ألا تظهر) + معتمد أقدم (يجب أن يظهر)
        $this->finalizedPayslip($employee, 2027, 6, 'draft', 9999);
        $this->finalizedPayslip($employee, 2027, 3, 'approved', 5000);

        $res = $this->actingAsToken($user)->getJson('/api/me/dashboard')->assertOk();

        $this->assertEquals(2027, $res->json('data.last_payslip.year'));
        $this->assertEquals(3, $res->json('data.last_payslip.month'));
        $this->assertEquals(5000, $res->json('data.last_payslip.net'));
    }

    public function test_last_payslip_null_when_no_finalized_run(): void
    {
        [$user, $employee] = $this->linkedUser();
        $this->finalizedPayslip($employee, 2027, 3, 'draft', 5000); // مسوّدة فقط

        $res = $this->actingAsToken($user)->getJson('/api/me/dashboard')->assertOk();
        $this->assertNull($res->json('data.last_payslip'));
    }

    public function test_dashboard_does_not_leak_another_employee(): void
    {
        [$user, $employee] = $this->linkedUser();
        // موظف آخر له راتب نهائي أحدث — يجب ألا يظهر في لوحتي.
        $other = Employee::factory()->create();
        $this->finalizedPayslip($other, 2027, 12, 'approved', 12345);
        $this->finalizedPayslip($employee, 2027, 1, 'approved', 3000);

        $res = $this->actingAsToken($user)->getJson('/api/me/dashboard')->assertOk();

        // راتبي أنا (3000) لا راتب الآخر (12345).
        $this->assertEquals(3000, $res->json('data.last_payslip.net'));
        $this->assertSame($employee->full_name_ar, $res->json('data.profile.name'));
    }

    public function test_dashboard_requires_permission(): void
    {
        [$user] = $this->linkedUser([]); // موظف مرتبط لكن بلا صلاحية

        $this->actingAsToken($user)->getJson('/api/me/dashboard')->assertStatus(403);
    }

    public function test_dashboard_forbidden_for_unlinked_user(): void
    {
        $user = $this->userWithPermissions(['dashboard.view_own']); // بلا موظف مرتبط

        $this->actingAsToken($user)->getJson('/api/me/dashboard')
            ->assertStatus(403)->assertJsonPath('errors.code', 'NO_LINKED_EMPLOYEE');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/me/dashboard')->assertStatus(401);
    }
}
