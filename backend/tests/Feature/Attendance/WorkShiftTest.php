<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Models\EmployeeShift;
use Modules\Attendance\Models\WorkShift;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class WorkShiftTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_can_create_and_list_work_shifts(): void
    {
        $admin = $this->userWithPermissions(['attendance.manual', 'attendance.view']);

        $this->actingAsToken($admin)->postJson('/api/work-shifts', [
            'name' => 'مسائي', 'start_time' => '16:00', 'end_time' => '23:00', 'grace_minutes' => 15,
            'weekdays' => [1, 2, 3, 4, 5],
        ])->assertCreated()->assertJsonPath('data.name', 'مسائي');

        $this->assertDatabaseHas('work_shifts', ['name' => 'مسائي']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'work_shift_created']);
        $this->actingAsToken($admin)->getJson('/api/work-shifts')->assertOk();
    }

    public function test_work_shift_time_format_is_validated(): void
    {
        $admin = $this->userWithPermissions(['attendance.manual']);

        $this->actingAsToken($admin)->postJson('/api/work-shifts', [
            'name' => 'خاطئ', 'start_time' => '8am', 'end_time' => '16:00',
        ])->assertStatus(422);
    }

    public function test_work_shift_write_requires_permission(): void
    {
        $viewer = $this->userWithPermissions(['attendance.view']);

        $this->actingAsToken($viewer)->postJson('/api/work-shifts', [
            'name' => 'x', 'start_time' => '08:00', 'end_time' => '16:00',
        ])->assertStatus(403);
    }

    public function test_employee_can_be_assigned_to_shift(): void
    {
        $admin = $this->userWithPermissions(['attendance.manual', 'attendance.view']);
        $employee = Employee::factory()->create();
        $shift = WorkShift::create(['name' => 'صباحي', 'start_time' => '08:00', 'end_time' => '16:00']);

        $this->actingAsToken($admin)->postJson("/api/employees/{$employee->id}/shifts", [
            'shift_id' => $shift->id, 'from_date' => '2026-01-01',
        ])->assertCreated();

        $this->assertDatabaseHas('employee_shifts', ['employee_id' => $employee->id, 'shift_id' => $shift->id]);
    }

    public function test_can_list_employee_shift_assignments(): void
    {
        $admin = $this->userWithPermissions(['attendance.manual', 'attendance.view']);
        $employee = Employee::factory()->create();
        $shift = WorkShift::create(['name' => 'صباحي', 'start_time' => '08:00', 'end_time' => '16:00']);
        EmployeeShift::create([
            'employee_id' => $employee->id, 'shift_id' => $shift->id, 'from_date' => '2026-01-01',
        ]);

        $res = $this->actingAsToken($admin)->getJson("/api/employees/{$employee->id}/shifts")
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'errors']);

        $this->assertSame($shift->id, $res->json('data.0.shift_id'));
    }

    public function test_attendance_routes_require_authentication(): void
    {
        $this->getJson('/api/attendance')->assertStatus(401);
        $this->getJson('/api/work-shifts')->assertStatus(401);
    }
}
