<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\EmployeeSalaryProfile;
use Modules\Payroll\Models\PayrollItem;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Models\PayrollRun;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class PayrollReportTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /**
     * ينشئ مسيراً بعناصر جاهزة (نتائج مجمّدة) — لاختبار منطق التقارير مباشرة.
     *
     * @param  array<int, array{branch:int, dept:int, basic?:float, allow?:float, ded:float, gross:float, net:float, emp?:int}>  $specs
     */
    private function runWithItems(array $specs, string $status = 'locked', int $year = 2027, int $month = 1, ?int $periodBranch = null): PayrollRun
    {
        $period = PayrollPeriod::create(['year' => $year, 'month' => $month, 'status' => $status, 'branch_id' => $periodBranch]);
        $run = PayrollRun::create(['payroll_period_id' => $period->id, 'status' => $status]);

        foreach ($specs as $s) {
            $employee = isset($s['emp'])
                ? Employee::find($s['emp'])
                : Employee::factory()->create(['branch_id' => $s['branch'], 'department_id' => $s['dept']]);

            PayrollItem::create([
                'payroll_run_id' => $run->id, 'employee_id' => $employee->id,
                'branch_id' => $s['branch'], 'department_id' => $s['dept'], 'currency' => 'SAR',
                'basic_salary' => $s['basic'] ?? $s['gross'], 'allowances_total' => $s['allow'] ?? 0,
                'deductions_total' => $s['ded'], 'gross_amount' => $s['gross'], 'net_amount' => $s['net'],
                'breakdown' => ['earnings' => [], 'deductions' => []],
            ]);
        }

        return $run;
    }

    public function test_cost_report_totals_are_correct(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $b = Branch::create(['name' => 'الرئيسي', 'code' => 'HQ1']);
        $d = Department::create(['branch_id' => $b->id, 'name' => 'التقاضي']);

        $this->runWithItems([
            ['branch' => $b->id, 'dept' => $d->id, 'basic' => 3000, 'allow' => 500, 'ded' => 100, 'gross' => 3500, 'net' => 3400],
            ['branch' => $b->id, 'dept' => $d->id, 'basic' => 5000, 'allow' => 1000, 'ded' => 200, 'gross' => 6000, 'net' => 5800],
        ]);

        $res = $this->actingAsToken($viewer)->getJson('/api/payroll-reports/cost')->assertOk();

        $this->assertEquals(2, $res->json('data.totals.headcount'));
        $this->assertEquals(1, $res->json('data.totals.runs'));
        $this->assertEquals(8000, $res->json('data.totals.basic'));
        $this->assertEquals(1500, $res->json('data.totals.allowances'));
        $this->assertEquals(300, $res->json('data.totals.deductions'));
        $this->assertEquals(9500, $res->json('data.totals.gross'));
        $this->assertEquals(9200, $res->json('data.totals.net'));
    }

    public function test_cost_report_group_by_branch(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $b1 = Branch::create(['name' => 'الرياض', 'code' => 'RUH']);
        $b2 = Branch::create(['name' => 'جدة', 'code' => 'JED']);
        $d1 = Department::create(['branch_id' => $b1->id, 'name' => 'قسم1']);
        $d2 = Department::create(['branch_id' => $b2->id, 'name' => 'قسم2']);

        // مسير متعدد الفروع (فترة بلا فرع محدد)
        $this->runWithItems([
            ['branch' => $b1->id, 'dept' => $d1->id, 'ded' => 0, 'gross' => 3000, 'net' => 3000],
            ['branch' => $b2->id, 'dept' => $d2->id, 'ded' => 0, 'gross' => 4000, 'net' => 4000],
            ['branch' => $b2->id, 'dept' => $d2->id, 'ded' => 0, 'gross' => 5000, 'net' => 5000],
        ]);

        $res = $this->actingAsToken($viewer)->getJson('/api/payroll-reports/cost?group_by=branch')->assertOk();

        $groups = collect($res->json('data.groups'))->keyBy('label');
        $this->assertEquals(3000, $groups['الرياض']['net']);
        $this->assertEquals(1, $groups['الرياض']['headcount']);
        $this->assertEquals(9000, $groups['جدة']['net']);
        $this->assertEquals(2, $groups['جدة']['headcount']);
    }

    public function test_cost_report_group_by_department(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $b = Branch::create(['name' => 'الرئيسي', 'code' => 'HQ2']);
        $d1 = Department::create(['branch_id' => $b->id, 'name' => 'التقاضي']);
        $d2 = Department::create(['branch_id' => $b->id, 'name' => 'الاستشارات']);

        $this->runWithItems([
            ['branch' => $b->id, 'dept' => $d1->id, 'ded' => 100, 'gross' => 3000, 'net' => 2900],
            ['branch' => $b->id, 'dept' => $d2->id, 'ded' => 0, 'gross' => 7000, 'net' => 7000],
        ]);

        $res = $this->actingAsToken($viewer)->getJson('/api/payroll-reports/cost?group_by=department')->assertOk();

        $groups = collect($res->json('data.groups'))->keyBy('label');
        $this->assertEquals(2900, $groups['التقاضي']['net']);
        $this->assertEquals(7000, $groups['الاستشارات']['net']);
    }

    public function test_cost_report_group_by_month_is_sorted(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $b = Branch::create(['name' => 'الرئيسي', 'code' => 'HQ3']);
        $d = Department::create(['branch_id' => $b->id, 'name' => 'عام']);

        $this->runWithItems([['branch' => $b->id, 'dept' => $d->id, 'ded' => 0, 'gross' => 3000, 'net' => 3000]], 'locked', 2027, 3);
        $this->runWithItems([['branch' => $b->id, 'dept' => $d->id, 'ded' => 0, 'gross' => 4000, 'net' => 4000]], 'locked', 2027, 1);

        $res = $this->actingAsToken($viewer)->getJson('/api/payroll-reports/cost?group_by=month')->assertOk();

        $groups = $res->json('data.groups');
        $this->assertSame(1, $groups[0]['key']['month']); // مرتّب تصاعدياً
        $this->assertSame(3, $groups[1]['key']['month']);
        $this->assertEquals(4000, $groups[0]['net']);
    }

    public function test_cost_report_filter_by_branch_isolates(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $b1 = Branch::create(['name' => 'الرياض', 'code' => 'RUH2']);
        $b2 = Branch::create(['name' => 'جدة', 'code' => 'JED2']);
        $d1 = Department::create(['branch_id' => $b1->id, 'name' => 'ق1']);
        $d2 = Department::create(['branch_id' => $b2->id, 'name' => 'ق2']);

        $this->runWithItems([
            ['branch' => $b1->id, 'dept' => $d1->id, 'ded' => 0, 'gross' => 3000, 'net' => 3000],
            ['branch' => $b2->id, 'dept' => $d2->id, 'ded' => 0, 'gross' => 9000, 'net' => 9000],
        ]);

        $res = $this->actingAsToken($viewer)->getJson("/api/payroll-reports/cost?branch_id={$b1->id}")->assertOk();

        // عزل الفرع: إجمالي فرع الرياض فقط، لا تسرّب من جدة.
        $this->assertEquals(1, $res->json('data.totals.headcount'));
        $this->assertEquals(3000, $res->json('data.totals.net'));
    }

    public function test_cost_report_filter_by_status(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $b = Branch::create(['name' => 'الرئيسي', 'code' => 'HQ4']);
        $d = Department::create(['branch_id' => $b->id, 'name' => 'عام']);

        $this->runWithItems([['branch' => $b->id, 'dept' => $d->id, 'ded' => 0, 'gross' => 3000, 'net' => 3000]], 'locked', 2027, 5);
        $this->runWithItems([['branch' => $b->id, 'dept' => $d->id, 'ded' => 0, 'gross' => 8000, 'net' => 8000]], 'draft', 2027, 6);

        $res = $this->actingAsToken($viewer)->getJson('/api/payroll-reports/cost?status=locked')->assertOk();

        $this->assertEquals(1, $res->json('data.totals.runs'));
        $this->assertEquals(3000, $res->json('data.totals.net'));
    }

    public function test_employee_report_lists_history(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $b = Branch::create(['name' => 'الرئيسي', 'code' => 'HQ5']);
        $d = Department::create(['branch_id' => $b->id, 'name' => 'عام']);
        $employee = Employee::factory()->create(['branch_id' => $b->id, 'department_id' => $d->id]);

        $this->runWithItems([['branch' => $b->id, 'dept' => $d->id, 'emp' => $employee->id, 'ded' => 100, 'gross' => 3000, 'net' => 2900]], 'locked', 2027, 1);
        $this->runWithItems([['branch' => $b->id, 'dept' => $d->id, 'emp' => $employee->id, 'ded' => 200, 'gross' => 3500, 'net' => 3300]], 'locked', 2027, 2);

        $res = $this->actingAsToken($viewer)->getJson("/api/payroll-reports/employees/{$employee->id}")->assertOk();

        $this->assertCount(2, $res->json('data.history'));
        $this->assertSame(2, $res->json('data.history.0.month')); // أحدث أولاً
        $this->assertEquals(6200, $res->json('data.totals.net'));
        $this->assertEquals(300, $res->json('data.totals.deductions'));
    }

    public function test_employee_report_filter_by_year(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $b = Branch::create(['name' => 'الرئيسي', 'code' => 'HQ6']);
        $d = Department::create(['branch_id' => $b->id, 'name' => 'عام']);
        $employee = Employee::factory()->create(['branch_id' => $b->id, 'department_id' => $d->id]);

        $this->runWithItems([['branch' => $b->id, 'dept' => $d->id, 'emp' => $employee->id, 'ded' => 0, 'gross' => 3000, 'net' => 3000]], 'locked', 2026, 12);
        $this->runWithItems([['branch' => $b->id, 'dept' => $d->id, 'emp' => $employee->id, 'ded' => 0, 'gross' => 4000, 'net' => 4000]], 'locked', 2027, 1);

        $res = $this->actingAsToken($viewer)->getJson("/api/payroll-reports/employees/{$employee->id}?year=2027")->assertOk();

        $this->assertCount(1, $res->json('data.history'));
        $this->assertEquals(4000, $res->json('data.totals.net'));
    }

    public function test_reports_read_only_frozen_results_after_lock(): void
    {
        // نزاهة تاريخية: بناء العنصر عبر محرّك الحساب الحقيقي ثم قفله،
        // وتغيير بيانات الموظف الحيّة (القسم + الراتب) — التقرير لا يتغيّر.
        $actor = $this->userWithPermissions(['payroll.create', 'payroll.approve', 'payroll.pay', 'payroll.view']);
        $branch = Branch::create(['name' => 'الرياض', 'code' => 'RUH3']);
        $deptA = Department::create(['branch_id' => $branch->id, 'name' => 'التقاضي']);
        $deptB = Department::create(['branch_id' => $branch->id, 'name' => 'الاستشارات']);
        $employee = Employee::factory()->create(['branch_id' => $branch->id, 'department_id' => $deptA->id]);
        EmployeeSalaryProfile::create(['employee_id' => $employee->id, 'basic_salary' => 3000, 'effective_from' => '2026-01-01', 'is_active' => true]);

        $period = PayrollPeriod::create(['year' => 2027, 'month' => 4, 'status' => 'draft', 'branch_id' => $branch->id]);
        $run = PayrollRun::create(['payroll_period_id' => $period->id, 'status' => 'draft']);

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/calculate")->assertOk();
        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/approve")->assertOk();
        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/lock")->assertOk();

        // قبل التغيير: القسم "التقاضي" يحمل 3000.
        $before = $this->actingAsToken($actor)->getJson("/api/payroll-reports/cost?group_by=department&department_id={$deptA->id}");
        $this->assertEquals(3000, $before->json('data.totals.net'));
        $this->assertSame('التقاضي', $before->json('data.groups.0.label'));

        // تغييرات حيّة بعد القفل: نقل القسم + رفع الراتب.
        $employee->update(['department_id' => $deptB->id]);
        EmployeeSalaryProfile::where('employee_id', $employee->id)->update(['is_active' => false]);
        EmployeeSalaryProfile::create(['employee_id' => $employee->id, 'basic_salary' => 9999, 'effective_from' => '2027-06-01', 'is_active' => true]);

        // بعد التغيير: التقرير التاريخي ثابت — لا يزال "التقاضي" 3000، ولا شيء في "الاستشارات".
        $afterA = $this->actingAsToken($actor)->getJson("/api/payroll-reports/cost?group_by=department&department_id={$deptA->id}");
        $this->assertEquals(3000, $afterA->json('data.totals.net'));
        $this->assertSame('التقاضي', $afterA->json('data.groups.0.label'));

        $afterB = $this->actingAsToken($actor)->getJson("/api/payroll-reports/cost?group_by=department&department_id={$deptB->id}");
        $this->assertEquals(0, $afterB->json('data.totals.net'));
        $this->assertCount(0, $afterB->json('data.groups'));
    }

    public function test_cost_report_records_audit(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $b = Branch::create(['name' => 'الرئيسي', 'code' => 'HQ7']);
        $d = Department::create(['branch_id' => $b->id, 'name' => 'عام']);
        $this->runWithItems([['branch' => $b->id, 'dept' => $d->id, 'ded' => 0, 'gross' => 3000, 'net' => 3000]]);

        $this->actingAsToken($viewer)->getJson('/api/payroll-reports/cost')->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'payroll_cost_report_viewed']);
    }

    public function test_employee_report_records_audit(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $employee = Employee::factory()->create();

        $this->actingAsToken($viewer)->getJson("/api/payroll-reports/employees/{$employee->id}")->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'payroll_employee_report_viewed', 'auditable_id' => $employee->id]);
    }

    public function test_reports_require_permission(): void
    {
        $noPerm = $this->userWithPermissions([]);
        $employee = Employee::factory()->create();

        $this->actingAsToken($noPerm)->getJson('/api/payroll-reports/cost')->assertStatus(403);
        $this->actingAsToken($noPerm)->getJson("/api/payroll-reports/employees/{$employee->id}")->assertStatus(403);
    }

    public function test_reports_require_authentication(): void
    {
        $this->getJson('/api/payroll-reports/cost')->assertStatus(401);
    }

    public function test_cost_report_rejects_invalid_group_by(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);

        $this->actingAsToken($viewer)->getJson('/api/payroll-reports/cost?group_by=tenant')
            ->assertStatus(422);
    }
}
