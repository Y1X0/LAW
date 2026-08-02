<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Models\EmployeeShift;
use Modules\Attendance\Models\WorkShift;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class AttendanceCheckInOutTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function employeeWithShift(): Employee
    {
        $employee = Employee::factory()->create();
        $shift = WorkShift::create([
            'name' => 'صباحي', 'start_time' => '08:00', 'end_time' => '16:00', 'grace_minutes' => 10,
        ]);
        EmployeeShift::create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'from_date' => '2026-01-01']);

        return $employee;
    }

    public function test_check_in_computes_late_minutes(): void
    {
        $actor = $this->userWithPermissions(['attendance.manual']);
        $employee = $this->employeeWithShift();

        // دخول 08:20 مع سماح حتى 08:10 → تأخير 10 دقائق، الحالة late
        $this->actingAsToken($actor)->postJson('/api/attendance/check-in', [
            'employee_id' => $employee->id, 'time' => '2026-01-06T08:20:00',
        ])->assertCreated()
            ->assertJsonPath('data.late_minutes', 10)
            ->assertJsonPath('data.status', 'late');
    }

    public function test_on_time_check_in_is_present(): void
    {
        $actor = $this->userWithPermissions(['attendance.manual']);
        $employee = $this->employeeWithShift();

        $this->actingAsToken($actor)->postJson('/api/attendance/check-in', [
            'employee_id' => $employee->id, 'time' => '2026-01-06T08:05:00', // ضمن السماح
        ])->assertCreated()
            ->assertJsonPath('data.late_minutes', 0)
            ->assertJsonPath('data.status', 'present');
    }

    public function test_check_out_computes_worked_and_overtime(): void
    {
        $actor = $this->userWithPermissions(['attendance.manual']);
        $employee = $this->employeeWithShift();

        $this->actingAsToken($actor)->postJson('/api/attendance/check-in', [
            'employee_id' => $employee->id, 'time' => '2026-01-06T08:20:00',
        ])->assertCreated();

        // خروج 17:00 → عمل 520 دقيقة، إضافي 60 دقيقة
        $this->actingAsToken($actor)->postJson('/api/attendance/check-out', [
            'employee_id' => $employee->id, 'time' => '2026-01-06T17:00:00',
        ])->assertOk()
            ->assertJsonPath('data.worked_minutes', 520)
            ->assertJsonPath('data.overtime_minutes', 60)
            ->assertJsonPath('data.early_leave_minutes', 0);
    }

    public function test_early_leave_is_computed(): void
    {
        $actor = $this->userWithPermissions(['attendance.manual']);
        $employee = $this->employeeWithShift();

        $this->actingAsToken($actor)->postJson('/api/attendance/check-in', [
            'employee_id' => $employee->id, 'time' => '2026-01-06T08:00:00',
        ])->assertCreated();

        $this->actingAsToken($actor)->postJson('/api/attendance/check-out', [
            'employee_id' => $employee->id, 'time' => '2026-01-06T15:00:00', // قبل 16:00 بساعة
        ])->assertOk()->assertJsonPath('data.early_leave_minutes', 60);
    }

    public function test_check_out_without_check_in_is_rejected(): void
    {
        $actor = $this->userWithPermissions(['attendance.manual']);
        $employee = Employee::factory()->create();

        $this->actingAsToken($actor)->postJson('/api/attendance/check-out', [
            'employee_id' => $employee->id, 'time' => '2026-01-06T17:00:00',
        ])->assertStatus(422);
    }

    /**
     * تصحيح: خروج قبل الدخول يُرفَض (422) — بدون هذا الحارس ينتج worked_minutes سالب
     * (diffInMinutes موقّع في Carbon 3) يُفسِد تقارير الحضور.
     */
    public function test_check_out_before_check_in_is_rejected(): void
    {
        $actor = $this->userWithPermissions(['attendance.manual']);
        $employee = $this->employeeWithShift();

        $this->actingAsToken($actor)->postJson('/api/attendance/check-in', [
            'employee_id' => $employee->id, 'time' => '2026-01-06T09:00:00',
        ])->assertCreated();

        // خروج 08:00 (قبل الدخول 09:00) → 422، ولا يُحفَظ زمن خروج سالب.
        $this->actingAsToken($actor)->postJson('/api/attendance/check-out', [
            'employee_id' => $employee->id, 'time' => '2026-01-06T08:00:00',
        ])->assertStatus(422);

        $this->assertDatabaseHas('attendance_records', [
            'employee_id' => $employee->id, 'check_out' => null,
        ]);
    }

    public function test_one_record_per_employee_per_day(): void
    {
        $actor = $this->userWithPermissions(['attendance.manual']);
        $employee = $this->employeeWithShift();

        $this->actingAsToken($actor)->postJson('/api/attendance/check-in', [
            'employee_id' => $employee->id, 'time' => '2026-01-06T08:00:00',
        ])->assertCreated();
        $this->actingAsToken($actor)->postJson('/api/attendance/check-in', [
            'employee_id' => $employee->id, 'time' => '2026-01-06T09:00:00',
        ])->assertCreated();

        $this->assertDatabaseCount('attendance_records', 1);
    }
}
