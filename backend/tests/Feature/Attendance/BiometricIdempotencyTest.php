<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Attendance\Biometric\PunchData;
use Modules\Attendance\Models\AttendanceLog;
use Modules\Attendance\Models\BiometricDevice;
use Modules\Attendance\Services\BiometricIngestionService;
use Modules\HR\Models\Employee;
use Tests\TestCase;

/**
 * منع التكرار (Issue #16): إعادة إرسال نفس النبضة (الجهاز/المستخدم/الوقت) لا تُنشئ
 * سجل Staging جديداً ولا سجل حضور مكرراً.
 */
class BiometricIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_punch_is_ignored(): void
    {
        $device = BiometricDevice::create(['name' => 'جهاز', 'vendor' => 'zkteco', 'api_mode' => 'push', 'is_active' => true]);
        $employee = Employee::factory()->create(['biometric_user_id' => '1001']);
        $ingestion = app(BiometricIngestionService::class);

        $punch = new PunchData('1001', Carbon::parse('2026-01-06 08:00:00'), 'in', 'fingerprint', ['pin' => '1001']);

        $first = $ingestion->ingest($device, $punch, 'push');
        $second = $ingestion->ingest($device, $punch, 'push'); // نفس النبضة تماماً

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id); // نفس السجل — لم يُنشأ جديد
        $this->assertDatabaseCount('attendance_logs', 1);
        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertSame(1, AttendanceLog::count());
    }

    public function test_matching_binds_employee_by_biometric_user_id(): void
    {
        $device = BiometricDevice::create(['name' => 'جهاز', 'vendor' => 'zkteco', 'api_mode' => 'push', 'is_active' => true]);
        $employee = Employee::factory()->create(['biometric_user_id' => '2002']);
        $ingestion = app(BiometricIngestionService::class);

        $log = $ingestion->ingest($device, new PunchData('2002', Carbon::parse('2026-01-06 08:00:00'), 'in'), 'push');

        $this->assertSame($employee->id, $log->employee_id);
        $this->assertSame('processed', $log->fresh()->status);
    }
}
