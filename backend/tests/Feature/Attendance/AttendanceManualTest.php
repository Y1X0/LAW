<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class AttendanceManualTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_manual_record_is_created_pending_approval(): void
    {
        $hr = $this->userWithPermissions(['attendance.manual']);
        $employee = Employee::factory()->create();

        $res = $this->actingAsToken($hr)->postJson('/api/attendance/manual', [
            'employee_id' => $employee->id,
            'work_date' => '2026-01-06',
            'status' => 'absent',
            'notes' => 'غياب بعذر',
        ])->assertCreated();

        $this->assertNull($res->json('data.approved_by'));
        $this->assertDatabaseHas('attendance_records', [
            'employee_id' => $employee->id, 'status' => 'absent', 'source' => 'manual',
        ]);
        $record = AttendanceRecord::where('employee_id', $employee->id)->first();
        $this->assertSame('2026-01-06', $record->work_date->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance_manual_recorded']);
    }

    public function test_manual_record_can_be_approved(): void
    {
        $hr = $this->userWithPermissions(['attendance.manual']);
        $approver = $this->userWithPermissions(['attendance.approve']);
        $employee = Employee::factory()->create();

        $record = AttendanceRecord::create([
            'employee_id' => $employee->id, 'work_date' => '2026-01-06', 'status' => 'present', 'source' => 'manual',
        ]);

        $this->actingAsToken($approver)->postJson("/api/attendance/{$record->id}/approve")
            ->assertOk();

        $this->assertNotNull($record->fresh()->approved_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance_approved']);
    }

    public function test_manual_record_requires_permission(): void
    {
        $viewer = $this->userWithPermissions(['attendance.view']);
        $employee = Employee::factory()->create();

        $this->actingAsToken($viewer)->postJson('/api/attendance/manual', [
            'employee_id' => $employee->id, 'work_date' => '2026-01-06', 'status' => 'present',
        ])->assertStatus(403);
    }

    public function test_approve_requires_approve_permission(): void
    {
        $hr = $this->userWithPermissions(['attendance.manual']);
        $employee = Employee::factory()->create();
        $record = AttendanceRecord::create([
            'employee_id' => $employee->id, 'work_date' => '2026-01-06', 'status' => 'present',
        ]);

        // يملك manual لكن ليس approve
        $this->actingAsToken($hr)->postJson("/api/attendance/{$record->id}/approve")->assertStatus(403);
    }

    public function test_manual_status_is_validated(): void
    {
        $hr = $this->userWithPermissions(['attendance.manual']);
        $employee = Employee::factory()->create();

        $this->actingAsToken($hr)->postJson('/api/attendance/manual', [
            'employee_id' => $employee->id, 'work_date' => '2026-01-06', 'status' => 'teleported',
        ])->assertStatus(422)->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }
}
