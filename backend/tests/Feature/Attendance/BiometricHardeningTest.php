<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Attendance\Biometric\PunchData;
use Modules\Attendance\Models\AttendanceLog;
use Modules\Attendance\Models\BiometricDevice;
use Modules\Attendance\Services\BiometricIngestionService;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * تحصين تكامل البصمة (متابعة #16): تحويل المنطقة الزمنية للجهاز إلى UTC،
 * والحذف الناعم الذي يحمي سجلات البصمة التاريخية من الفقدان.
 */
class BiometricHardeningTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_device_punch_time_is_normalized_to_utc_by_device_timezone(): void
    {
        // جهاز في الرياض (+03 ثابتة بلا توقيت صيفي) بلا موظف مطابق → السجل يبقى خام.
        $device = BiometricDevice::create([
            'name' => 'بوابة الرياض', 'vendor' => 'zkteco', 'api_mode' => 'push',
            'timezone' => 'Asia/Riyadh', 'is_active' => true,
        ]);

        $punch = new PunchData('9001', Carbon::parse('2026-02-01 08:00:00'), 'in');
        app(BiometricIngestionService::class)->ingest($device, $punch, 'push');

        // 08:00 بتوقيت الرياض = 05:00 UTC.
        $log = AttendanceLog::where('device_id', $device->id)->firstOrFail();
        $this->assertSame('05:00', $log->punch_time->clone()->utc()->format('H:i'));
    }

    public function test_no_device_timezone_leaves_punch_time_unchanged(): void
    {
        $device = BiometricDevice::create([
            'name' => 'جهاز', 'vendor' => 'zkteco', 'api_mode' => 'push', 'is_active' => true,
        ]);

        $punch = new PunchData('9002', Carbon::parse('2026-02-01 08:00:00'), 'in');
        app(BiometricIngestionService::class)->ingest($device, $punch, 'push');

        $log = AttendanceLog::where('device_id', $device->id)->firstOrFail();
        $this->assertSame('08:00', $log->punch_time->clone()->utc()->format('H:i'));
    }

    public function test_timezone_dedup_stays_idempotent_after_conversion(): void
    {
        $device = BiometricDevice::create([
            'name' => 'بوابة', 'vendor' => 'zkteco', 'api_mode' => 'push',
            'timezone' => 'Asia/Riyadh', 'is_active' => true,
        ]);
        $service = app(BiometricIngestionService::class);
        $punch = new PunchData('9003', Carbon::parse('2026-02-01 08:00:00'), 'in');

        $service->ingest($device, $punch, 'push');
        $service->ingest($device, $punch, 'push'); // إعادة إرسال

        $this->assertSame(1, AttendanceLog::where('device_id', $device->id)->count());
    }

    public function test_deleting_device_is_soft_and_preserves_raw_logs(): void
    {
        $admin = $this->userWithPermissions(['attendance.devices']);
        $device = BiometricDevice::create([
            'name' => 'جهاز', 'vendor' => 'zkteco', 'api_mode' => 'push', 'is_active' => true,
        ]);
        AttendanceLog::create([
            'device_id' => $device->id, 'biometric_user_id' => 'X1',
            'punch_time' => '2026-02-01 08:00:00', 'punch_type' => 'in',
            'source' => 'push', 'status' => 'unmatched',
        ]);

        // حذف على مستوى النموذج (لا مسار حذف عبر الـ API في #28) — حذف ناعم.
        $device->delete();

        $this->assertSoftDeleted('biometric_devices', ['id' => $device->id]);
        $this->assertSame(1, AttendanceLog::where('device_id', $device->id)->count());
    }

    public function test_device_can_be_created_with_timezone_and_rejects_invalid(): void
    {
        $admin = $this->userWithPermissions(['attendance.devices']);

        $this->actingAsToken($admin)->postJson('/api/biometric/devices', [
            'name' => 'الرياض', 'vendor' => 'zkteco', 'api_mode' => 'push', 'timezone' => 'Asia/Riyadh',
        ])->assertCreated();
        $this->assertDatabaseHas('biometric_devices', ['name' => 'الرياض', 'timezone' => 'Asia/Riyadh']);

        $this->actingAsToken($admin)->postJson('/api/biometric/devices', [
            'name' => 'خاطئ', 'vendor' => 'zkteco', 'api_mode' => 'push', 'timezone' => 'Mars/Phobos',
        ])->assertStatus(422);
    }
}
