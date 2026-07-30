<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Branch;
use Modules\HR\Models\Employee;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Modules\Payroll\Models\EmployeeSalaryProfile;
use Modules\Payroll\Models\PayrollLeaveSummary;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Models\PayrollRun;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class PayrollLeaveSnapshotTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function unpaidLeave(Employee $e, string $from, string $to): void
    {
        $type = LeaveType::firstOrCreate(['code' => 'unpaid'], ['name' => 'بدون راتب', 'is_paid' => false, 'consumes_balance' => false]);
        LeaveRequest::create([
            'employee_id' => $e->id, 'leave_type_id' => $type->id,
            'start_date' => $from, 'end_date' => $to, 'days' => 1, 'status' => 'approved',
        ]);
    }

    private function withProfile(Employee $e): void
    {
        EmployeeSalaryProfile::create(['employee_id' => $e->id, 'basic_salary' => 1000, 'effective_from' => '2026-01-01', 'is_active' => true]);
    }

    /** @return array{0:PayrollRun,1:Employee} */
    private function scenario(string $status = 'draft'): array
    {
        $employee = Employee::factory()->create();
        $this->withProfile($employee);
        $this->unpaidLeave($employee, '2026-06-08', '2026-06-10'); // اثنين→أربعاء = 3 أيام

        $period = PayrollPeriod::create(['year' => 2026, 'month' => 6, 'status' => $status]);
        $run = PayrollRun::create(['payroll_period_id' => $period->id, 'status' => $status]);

        return [$run, $employee];
    }

    public function test_snapshot_builds_leave_summary(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        [$run, $employee] = $this->scenario();

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/leave-snapshot")
            ->assertOk()->assertJsonPath('data.employees', 1);

        $summary = PayrollLeaveSummary::where('payroll_run_id', $run->id)->firstOrFail();
        $this->assertEquals(3, (float) $summary->unpaid_leave_days);
        $this->assertEquals(0, (float) $summary->paid_leave_days);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payroll_leave_snapshotted']);
    }

    public function test_snapshot_freezes_against_later_leave_changes(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        [$run, $employee] = $this->scenario();

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/leave-snapshot")->assertOk();
        $summary = PayrollLeaveSummary::where('payroll_run_id', $run->id)->firstOrFail();
        $this->assertEquals(3, (float) $summary->unpaid_leave_days);

        // إجازة إضافية معتمدة لاحقاً — اللقطة المحفوظة لا تتغيّر.
        $this->unpaidLeave($employee, '2026-06-15', '2026-06-15');
        $this->assertEquals(3, (float) $summary->fresh()->unpaid_leave_days);

        // إعادة اللقطة (مسودة) تُحدّث.
        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/leave-snapshot")->assertOk();
        $this->assertEquals(4, (float) $summary->fresh()->unpaid_leave_days);
        $this->assertDatabaseCount('payroll_leave_summaries', 1);
    }

    public function test_snapshot_does_not_modify_leave(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        [$run] = $this->scenario();
        $before = LeaveRequest::count();

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/leave-snapshot")->assertOk();

        $this->assertSame($before, LeaveRequest::count());
    }

    public function test_cannot_snapshot_approved_run(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        [$run] = $this->scenario('approved');

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/leave-snapshot")
            ->assertStatus(422);
        $this->assertDatabaseCount('payroll_leave_summaries', 0);
    }

    public function test_employee_without_salary_profile_is_excluded(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        $with = Employee::factory()->create();
        $this->withProfile($with);
        $this->unpaidLeave($with, '2026-06-08', '2026-06-10');
        $without = Employee::factory()->create(); // بلا ملف راتب

        $period = PayrollPeriod::create(['year' => 2026, 'month' => 6, 'status' => 'draft']);
        $run = PayrollRun::create(['payroll_period_id' => $period->id, 'status' => 'draft']);

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/leave-snapshot")
            ->assertOk()->assertJsonPath('data.employees', 1);

        $this->assertDatabaseHas('payroll_leave_summaries', ['employee_id' => $with->id]);
        $this->assertDatabaseMissing('payroll_leave_summaries', ['employee_id' => $without->id]);
    }

    public function test_branch_scoped_period_excludes_other_branches(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        $hq = Employee::factory()->create();
        $other = Employee::factory()->create(['branch_id' => Branch::create(['name' => 'فرع', 'code' => 'BR2'])->id]);
        foreach ([$hq, $other] as $e) {
            $this->withProfile($e);
        }

        $period = PayrollPeriod::create(['year' => 2026, 'month' => 6, 'status' => 'draft', 'branch_id' => $hq->branch_id]);
        $run = PayrollRun::create(['payroll_period_id' => $period->id, 'status' => 'draft']);

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/leave-snapshot")
            ->assertOk()->assertJsonPath('data.employees', 1);

        $this->assertDatabaseHas('payroll_leave_summaries', ['employee_id' => $hq->id]);
        $this->assertDatabaseMissing('payroll_leave_summaries', ['employee_id' => $other->id]);
    }

    public function test_snapshot_requires_create_permission(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        [$run] = $this->scenario();

        $this->actingAsToken($viewer)->postJson("/api/payroll-runs/{$run->id}/leave-snapshot")
            ->assertStatus(403);
    }

    public function test_can_list_run_leave_summaries(): void
    {
        $actor = $this->userWithPermissions(['payroll.create', 'payroll.view']);
        [$run] = $this->scenario();
        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/leave-snapshot")->assertOk();

        $this->actingAsToken($actor)->getJson("/api/payroll-runs/{$run->id}/leave-summaries")
            ->assertOk()->assertJsonPath('data.0.payroll_run_id', $run->id);
    }
}
