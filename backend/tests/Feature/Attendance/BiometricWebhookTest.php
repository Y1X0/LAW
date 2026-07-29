<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Models\AttendanceLog;
use Modules\Attendance\Models\BiometricDevice;
use Modules\Attendance\Models\EmployeeShift;
use Modules\Attendance\Models\WorkShift;
use Modules\Core\Models\AuditLog;
use Modules\HR\Models\Employee;
use Tests\TestCase;

/**
 * استقبال Push/Webhook للبصمة (Issue #16): مصادقة الجهاز، الحفظ الخام،
 * المطابقة والمعالجة إلى attendance_records بمصدر biometric، ومعالجة غير المطابَق.
 */
class BiometricWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function device(): BiometricDevice
    {
        return BiometricDevice::create([
            'name' => 'جهاز الاستقبال', 'vendor' => 'zkteco', 'api_mode' => 'push',
            'auth_token' => 'secret-token', 'is_active' => true,
        ]);
    }

    private function employeeWithShift(string $bioId): Employee
    {
        $employee = Employee::factory()->create(['biometric_user_id' => $bioId]);
        $shift = WorkShift::create(['name' => 'صباحي', 'start_time' => '08:00', 'end_time' => '16:00', 'grace_minutes' => 10]);
        EmployeeShift::create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'from_date' => '2026-01-01']);

        return $employee;
    }

    public function test_valid_push_ingests_matches_and_feeds_attendance_engine(): void
    {
        $device = $this->device();
        $employee = $this->employeeWithShift('1001');

        $this->withHeaders(['Authorization' => 'Bearer secret-token'])
            ->postJson("/api/biometric/devices/{$device->id}/webhook", [
                'records' => [
                    ['pin' => '1001', 'timestamp' => '2026-01-06 08:20:00', 'status' => '0', 'verify' => '1'],
                    ['pin' => '1001', 'timestamp' => '2026-01-06 17:00:00', 'status' => '1'],
                ],
            ])->assertOk()->assertJsonPath('data.received', 2);

        // Staging: نبضتان مُعالَجتان
        $this->assertDatabaseCount('attendance_logs', 2);
        $this->assertSame(2, AttendanceLog::where('status', 'processed')->count());

        // محرك الحضور: سجل يومي واحد بمصدر biometric (أول دخول/آخر خروج)
        $this->assertDatabaseHas('attendance_records', [
            'employee_id' => $employee->id, 'source' => 'biometric', 'status' => 'late', 'overtime_minutes' => 60,
        ]);
        $this->assertDatabaseCount('attendance_records', 1);
    }

    public function test_invalid_device_token_is_rejected(): void
    {
        $device = $this->device();

        $this->withHeaders(['Authorization' => 'Bearer wrong-token'])
            ->postJson("/api/biometric/devices/{$device->id}/webhook", [
                'records' => [['pin' => '1001', 'timestamp' => '2026-01-06 08:00:00', 'status' => '0']],
            ])->assertStatus(401);

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_unmatched_punch_is_pending_with_hr_alert(): void
    {
        $device = $this->device();
        // لا يوجد موظف بهذا المعرّف

        $this->withHeaders(['Authorization' => 'Bearer secret-token'])
            ->postJson("/api/biometric/devices/{$device->id}/webhook", [
                'records' => [['pin' => '9999', 'timestamp' => '2026-01-06 08:00:00', 'status' => '0']],
            ])->assertOk();

        $this->assertDatabaseHas('attendance_logs', ['biometric_user_id' => '9999', 'status' => 'unmatched', 'employee_id' => null]);
        $this->assertDatabaseCount('attendance_records', 0);
        $this->assertTrue(AuditLog::where('action', 'biometric_log_unmatched')->exists());
    }
}
