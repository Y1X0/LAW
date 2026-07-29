<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class AttendanceListingTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_can_list_and_filter_records(): void
    {
        $viewer = $this->userWithPermissions(['attendance.view']);
        $employee = Employee::factory()->create();
        AttendanceRecord::create(['employee_id' => $employee->id, 'work_date' => '2026-01-06', 'status' => 'present']);
        AttendanceRecord::create(['employee_id' => $employee->id, 'work_date' => '2026-01-07', 'status' => 'absent']);

        $this->actingAsToken($viewer)->getJson('/api/attendance')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['page', 'per_page', 'total', 'total_pages'], 'errors']);

        $res = $this->actingAsToken($viewer)->getJson('/api/attendance?status=absent')->assertOk();
        $this->assertSame(1, $res->json('meta.total'));

        $res = $this->actingAsToken($viewer)->getJson('/api/attendance?date=2026-01-06')->assertOk();
        $this->assertSame(1, $res->json('meta.total'));
    }

    public function test_listing_requires_view_permission(): void
    {
        $employee = Employee::factory()->create();
        $noPerm = User::factory()->create();

        $this->actingAsToken($noPerm)->getJson('/api/attendance')->assertStatus(403);
    }

    public function test_record_belongs_to_employee(): void
    {
        $employee = Employee::factory()->create();
        $record = AttendanceRecord::create(['employee_id' => $employee->id, 'work_date' => '2026-01-06', 'status' => 'present']);

        $this->assertSame($employee->id, $record->employee->id);
    }
}
