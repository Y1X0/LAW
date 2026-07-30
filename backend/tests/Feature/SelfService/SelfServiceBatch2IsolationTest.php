<?php

namespace Tests\Feature\SelfService;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * مراجعة تكاملية للدفعة #50–#52: عزل بيانات الموظفين + عزل الصلاحيات
 * عبر الحضور/الإجازات/الملف الذاتي.
 */
class SelfServiceBatch2IsolationTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private const ALL = ['attendance.view_own', 'leave.view_own', 'leave.request_own', 'profile.update_own'];

    private function linked(array $perms): array
    {
        $user = $this->userWithPermissions($perms);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        return [$user, $employee];
    }

    public function test_cross_employee_isolation_attendance_and_profile(): void
    {
        [$userA, $empA] = $this->linked(self::ALL);
        [$userB, $empB] = $this->linked(self::ALL);

        AttendanceRecord::create(['employee_id' => $empA->id, 'work_date' => '2027-03-01', 'status' => 'present']);
        AttendanceRecord::create(['employee_id' => $empB->id, 'work_date' => '2027-03-01', 'status' => 'absent']);
        AttendanceRecord::create(['employee_id' => $empB->id, 'work_date' => '2027-03-02', 'status' => 'absent']);

        // كل موظف يرى حضوره فقط.
        $this->assertCount(1, $this->actingAsToken($userA)->getJson('/api/me/attendance')->json('data'));
        $this->assertCount(2, $this->actingAsToken($userB)->getJson('/api/me/attendance')->json('data'));

        // تعديل A لملفه لا يمسّ B.
        $this->actingAsToken($userA)->patchJson('/api/me/profile', ['phone' => '0500000009'])->assertOk();
        $this->assertSame('0500000009', $empA->fresh()->phone);
        $this->assertNotSame('0500000009', $empB->fresh()->phone);
    }

    public function test_permission_isolation_across_new_features(): void
    {
        [$user] = $this->linked(['attendance.view_own']); // حضور فقط

        $this->actingAsToken($user)->getJson('/api/me/attendance')->assertOk();
        $this->actingAsToken($user)->getJson('/api/me/leave/balance')->assertStatus(403);
        $this->actingAsToken($user)->getJson('/api/me/profile')->assertStatus(403);
    }
}
