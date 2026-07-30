<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Core\Models\Branch;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\EmployeeSalaryComponent;
use Modules\Payroll\Models\EmployeeSalaryProfile;
use Modules\Payroll\Models\PayrollAttendanceSummary;
use Modules\Payroll\Models\PayrollItem;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\SalaryComponent;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class PayrollCalculationIsolationTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function withProfile(Employee $e, float $basic = 3000): void
    {
        EmployeeSalaryProfile::create(['employee_id' => $e->id, 'basic_salary' => $basic, 'effective_from' => '2026-01-01', 'is_active' => true]);
    }

    private function draftRun(?int $branchId = null): PayrollRun
    {
        $period = PayrollPeriod::create(['year' => 2026, 'month' => 6, 'status' => 'draft', 'branch_id' => $branchId]);

        return PayrollRun::create(['payroll_period_id' => $period->id, 'status' => 'draft']);
    }

    public function test_uses_frozen_snapshot_not_live_attendance(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        $employee = Employee::factory()->create();
        $this->withProfile($employee, 3000);
        $run = $this->draftRun();

        // اللقطة المجمّدة تقول: غياب يوم — رغم عدم وجود أي سجل حضور حيّ.
        PayrollAttendanceSummary::create([
            'payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'absent_days' => 1,
        ]);

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/calculate")->assertOk();

        // خصم الغياب طُبّق من اللقطة (يومي 100)، لا من الحضور الحيّ.
        $item = PayrollItem::where('payroll_run_id', $run->id)->firstOrFail();
        $this->assertEquals(100.0, (float) $item->deductions_total);
        $this->assertEquals(2900.0, (float) $item->net_amount);
    }

    public function test_calculation_does_not_modify_sources(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        $employee = Employee::factory()->create();
        $this->withProfile($employee, 3000);
        AttendanceRecord::create(['employee_id' => $employee->id, 'work_date' => '2026-06-01', 'status' => 'present', 'worked_minutes' => 480]);
        $c = SalaryComponent::create(['name' => 'h', 'code' => 'h', 'type' => 'allowance', 'value_type' => 'fixed']);
        EmployeeSalaryComponent::create(['employee_id' => $employee->id, 'salary_component_id' => $c->id, 'value' => 100, 'effective_from' => '2026-01-01', 'is_active' => true]);
        $run = $this->draftRun();

        $beforeAtt = AttendanceRecord::count();
        $beforeComp = EmployeeSalaryComponent::count();

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/calculate")->assertOk();

        // المحرّك قراءة فقط — لا يعدّل الحضور أو المكوّنات.
        $this->assertSame($beforeAtt, AttendanceRecord::count());
        $this->assertSame($beforeComp, EmployeeSalaryComponent::count());
        $this->assertTrue((bool) EmployeeSalaryComponent::where('employee_id', $employee->id)->where('is_active', true)->exists());
    }

    public function test_employee_without_salary_profile_is_excluded(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        $with = Employee::factory()->create();
        $this->withProfile($with);
        $without = Employee::factory()->create();
        $run = $this->draftRun();

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/calculate")
            ->assertOk()->assertJsonPath('data.employees', 1);

        $this->assertDatabaseHas('payroll_items', ['employee_id' => $with->id]);
        $this->assertDatabaseMissing('payroll_items', ['employee_id' => $without->id]);
    }

    public function test_branch_scoped_period_excludes_other_branches(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        $hq = Employee::factory()->create();
        $other = Employee::factory()->create(['branch_id' => Branch::create(['name' => 'فرع', 'code' => 'BR2'])->id]);
        foreach ([$hq, $other] as $e) {
            $this->withProfile($e);
        }
        $run = $this->draftRun($hq->branch_id);

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/calculate")
            ->assertOk()->assertJsonPath('data.employees', 1);

        $this->assertDatabaseHas('payroll_items', ['employee_id' => $hq->id]);
        $this->assertDatabaseMissing('payroll_items', ['employee_id' => $other->id]);
    }
}
