<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\EmployeeSalaryProfile;
use Modules\Payroll\Models\PayrollAttendanceSummary;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Models\PayrollRun;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class PayrollAttendanceSnapshotTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** @return array{0:PayrollRun,1:Employee} */
    private function scenario(): array
    {
        $employee = Employee::factory()->create();
        EmployeeSalaryProfile::create([
            'employee_id' => $employee->id, 'basic_salary' => 1000, 'effective_from' => '2026-01-01', 'is_active' => true,
        ]);
        AttendanceRecord::create(['employee_id' => $employee->id, 'work_date' => '2026-06-01', 'status' => 'present', 'worked_minutes' => 480]);
        AttendanceRecord::create(['employee_id' => $employee->id, 'work_date' => '2026-06-02', 'status' => 'absent']);

        $period = PayrollPeriod::create(['year' => 2026, 'month' => 6, 'status' => 'draft']);
        $run = PayrollRun::create(['payroll_period_id' => $period->id, 'status' => 'draft']);

        return [$run, $employee];
    }

    public function test_snapshot_builds_summary_for_run_employees(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        [$run, $employee] = $this->scenario();

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/attendance-snapshot")
            ->assertOk()->assertJsonPath('data.employees', 1);

        $this->assertDatabaseHas('payroll_attendance_summaries', [
            'payroll_run_id' => $run->id, 'employee_id' => $employee->id,
            'total_work_days' => 1, 'absent_days' => 1,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payroll_attendance_snapshotted']);
    }

    public function test_snapshot_freezes_numbers_against_later_attendance_changes(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        [$run, $employee] = $this->scenario();

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/attendance-snapshot")->assertOk();
        $summary = PayrollAttendanceSummary::where('payroll_run_id', $run->id)->firstOrFail();
        $this->assertSame(1, $summary->total_work_days);

        // تغيّر الحضور لاحقاً (يوم عمل إضافي) — اللقطة المحفوظة لا تتغيّر تلقائياً.
        AttendanceRecord::create(['employee_id' => $employee->id, 'work_date' => '2026-06-03', 'status' => 'present', 'worked_minutes' => 480]);
        $this->assertSame(1, $summary->fresh()->total_work_days);

        // إعادة اللقطة (المسير مسودة) تُحدّث الأرقام.
        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/attendance-snapshot")->assertOk();
        $this->assertSame(2, $summary->fresh()->total_work_days);
        $this->assertDatabaseCount('payroll_attendance_summaries', 1); // idempotent — لا تكرار
    }

    public function test_snapshot_does_not_modify_attendance(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        [$run] = $this->scenario();
        $before = AttendanceRecord::count();

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/attendance-snapshot")->assertOk();

        // Payroll يقرأ فقط — لا كتابة/تعديل في جداول الحضور.
        $this->assertSame($before, AttendanceRecord::count());
    }

    public function test_snapshot_requires_create_permission(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        [$run] = $this->scenario();

        $this->actingAsToken($viewer)->postJson("/api/payroll-runs/{$run->id}/attendance-snapshot")
            ->assertStatus(403);
    }

    public function test_can_list_run_summaries(): void
    {
        $actor = $this->userWithPermissions(['payroll.create', 'payroll.view']);
        [$run] = $this->scenario();
        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/attendance-snapshot")->assertOk();

        $this->actingAsToken($actor)->getJson("/api/payroll-runs/{$run->id}/attendance-summaries")
            ->assertOk()->assertJsonPath('data.0.payroll_run_id', $run->id);
    }
}
