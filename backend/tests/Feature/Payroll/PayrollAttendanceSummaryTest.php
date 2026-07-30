<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class PayrollAttendanceSummaryTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function seedMonth(Employee $employee): void
    {
        AttendanceRecord::create(['employee_id' => $employee->id, 'work_date' => '2026-06-01', 'status' => 'present', 'worked_minutes' => 480]);
        AttendanceRecord::create(['employee_id' => $employee->id, 'work_date' => '2026-06-02', 'status' => 'late', 'worked_minutes' => 465, 'late_minutes' => 15]);
        AttendanceRecord::create(['employee_id' => $employee->id, 'work_date' => '2026-06-03', 'status' => 'absent']);
        AttendanceRecord::create(['employee_id' => $employee->id, 'work_date' => '2026-06-04', 'status' => 'present', 'worked_minutes' => 480, 'overtime_minutes' => 60]);
        // شهر آخر — يجب ألا يُحتسب
        AttendanceRecord::create(['employee_id' => $employee->id, 'work_date' => '2026-05-20', 'status' => 'present', 'worked_minutes' => 480]);
    }

    public function test_preview_aggregates_monthly_attendance(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $employee = Employee::factory()->create();
        $this->seedMonth($employee);

        $this->actingAsToken($viewer)->getJson("/api/employees/{$employee->id}/attendance-summary?year=2026&month=6")
            ->assertOk()
            ->assertJsonPath('data.total_work_days', 3)   // present + late + present
            ->assertJsonPath('data.absent_days', 1)
            ->assertJsonPath('data.late_minutes', 15)
            ->assertJsonPath('data.overtime_minutes', 60)
            ->assertJsonPath('data.overtime_hours', 1)
            ->assertJsonPath('data.worked_minutes', 1425);
    }

    public function test_preview_requires_year_and_month(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $employee = Employee::factory()->create();

        $this->actingAsToken($viewer)->getJson("/api/employees/{$employee->id}/attendance-summary")
            ->assertStatus(422);
    }

    public function test_preview_requires_permission(): void
    {
        $noPerm = $this->userWithPermissions([]);
        $employee = Employee::factory()->create();

        $this->actingAsToken($noPerm)->getJson("/api/employees/{$employee->id}/attendance-summary?year=2026&month=6")
            ->assertStatus(403);
    }

    public function test_empty_month_returns_zeros(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $employee = Employee::factory()->create();

        $this->actingAsToken($viewer)->getJson("/api/employees/{$employee->id}/attendance-summary?year=2026&month=6")
            ->assertOk()
            ->assertJsonPath('data.total_work_days', 0)
            ->assertJsonPath('data.absent_days', 0)
            ->assertJsonPath('data.overtime_hours', 0);
    }
}
