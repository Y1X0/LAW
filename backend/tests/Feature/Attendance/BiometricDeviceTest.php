<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Models\AttendanceLog;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Models\BiometricDevice;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class BiometricDeviceTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_can_create_and_list_devices(): void
    {
        $admin = $this->userWithPermissions(['attendance.devices']);

        $res = $this->actingAsToken($admin)->postJson('/api/biometric/devices', [
            'name' => 'بوابة الفرع', 'vendor' => 'zkteco', 'api_mode' => 'push',
            'serial_number' => 'ZK-001', 'secret' => 'device-secret-1',
        ])->assertCreated()->assertJsonPath('data.vendor', 'zkteco');

        // المفتاح السري لا يُسرَّب في الاستجابة.
        $this->assertNull($res->json('data.secret'));

        $this->assertDatabaseHas('biometric_devices', ['name' => 'بوابة الفرع', 'vendor' => 'zkteco']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'biometric_device_created']);
        $this->actingAsToken($admin)->getJson('/api/biometric/devices')->assertOk();
    }

    public function test_device_vendor_is_validated(): void
    {
        $admin = $this->userWithPermissions(['attendance.devices']);

        $this->actingAsToken($admin)->postJson('/api/biometric/devices', [
            'name' => 'x', 'vendor' => 'nokia', 'secret' => 'device-secret-1',
        ])->assertStatus(422)->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_device_management_requires_permission(): void
    {
        $viewer = $this->userWithPermissions(['attendance.view']);

        $this->actingAsToken($viewer)->getJson('/api/biometric/devices')->assertStatus(403);
        $this->actingAsToken($viewer)->postJson('/api/biometric/devices', [
            'name' => 'x', 'vendor' => 'zkteco', 'secret' => 'device-secret-1',
        ])->assertStatus(403);
    }

    public function test_device_routes_require_authentication(): void
    {
        $this->getJson('/api/biometric/devices')->assertStatus(401);
    }

    public function test_deleting_device_preserves_raw_punch_history(): void
    {
        $admin = $this->userWithPermissions(['attendance.devices']);
        $device = BiometricDevice::create([
            'name' => 'بوابة', 'vendor' => 'zkteco', 'api_mode' => 'push', 'secret' => 'device-secret-1',
        ]);
        AttendanceLog::create([
            'device_id' => $device->id, 'biometric_user_id' => 'U1',
            'punch_time' => '2026-02-01 08:00:00', 'punch_type' => 'check_in', 'status' => 'processed',
        ]);

        $this->actingAsToken($admin)->deleteJson("/api/biometric/devices/{$device->id}")->assertOk();

        // الجهاز محذوف ناعماً؛ سجلّ البصمة الخام باقٍ (لا فقدان تاريخ).
        $this->assertSoftDeleted('biometric_devices', ['id' => $device->id]);
        $this->assertSame(1, AttendanceLog::where('device_id', $device->id)->count());
    }

    public function test_device_timezone_is_converted_to_utc(): void
    {
        $device = BiometricDevice::create([
            'name' => 'الرياض', 'vendor' => 'zkteco', 'api_mode' => 'push',
            'secret' => 'device-secret-1', 'timezone' => 'Asia/Riyadh', // ثابتة +03 بلا توقيت صيفي
        ]);
        $employee = Employee::factory()->create(['biometric_user_id' => 'TZ1']);

        $this->withHeaders(['X-Device-Secret' => 'device-secret-1'])
            ->postJson("/api/biometric/devices/{$device->id}/webhook", [
                'records' => [['pin' => 'TZ1', 'time' => '2026-02-01 08:00:00', 'status' => 0]],
            ])->assertStatus(202);

        // 08:00 بتوقيت الرياض (+3) = 05:00 UTC.
        $record = AttendanceRecord::where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('05:00', $record->check_in->clone()->utc()->format('H:i'));
    }

    public function test_devices_permission_does_not_expose_attendance_data(): void
    {
        // من يملك attendance.devices فقط لا يستطيع قراءة سجلات الحضور.
        $deviceAdmin = $this->userWithPermissions(['attendance.devices']);

        $this->actingAsToken($deviceAdmin)->getJson('/api/biometric/devices')->assertOk();
        $this->actingAsToken($deviceAdmin)->getJson('/api/attendance')->assertStatus(403);
    }

    public function test_manual_sync_pull_runs_without_error(): void
    {
        $admin = $this->userWithPermissions(['attendance.devices']);
        $device = BiometricDevice::create([
            'name' => 'بوابة', 'vendor' => 'zkteco', 'api_mode' => 'pull', 'secret' => 'device-secret-1',
        ]);

        // محوّل ZKTeco الأساسي يُعيد [] للـ Pull (لا جهاز فعلي) — يجب أن تمرّ المزامنة بسلام.
        $this->actingAsToken($admin)->postJson("/api/biometric/devices/{$device->id}/sync")
            ->assertOk()
            ->assertJsonPath('data.ingested', 0);

        $this->assertNotNull($device->fresh()->last_sync_at);
        $this->assertSame('online', $device->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'biometric_pulled']);
    }
}
