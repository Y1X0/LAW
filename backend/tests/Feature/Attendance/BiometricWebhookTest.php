<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Models\AttendanceLog;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Models\BiometricDevice;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class BiometricWebhookTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function device(array $overrides = []): BiometricDevice
    {
        return BiometricDevice::create(array_merge([
            'name' => 'بوابة الرئيسي', 'vendor' => 'zkteco', 'api_mode' => 'push',
            'secret' => 'super-secret-key', 'is_active' => true,
        ], $overrides));
    }

    public function test_push_webhook_stores_staging_and_creates_biometric_attendance(): void
    {
        $device = $this->device();
        $employee = Employee::factory()->create(['biometric_user_id' => 'U1001']);

        $this->withHeaders(['X-Device-Secret' => 'super-secret-key'])
            ->postJson("/api/biometric/devices/{$device->id}/webhook", [
                'records' => [
                    ['pin' => 'U1001', 'time' => '2026-02-01 08:00:00', 'status' => 0, 'verify' => 'fingerprint'],
                    ['pin' => 'U1001', 'time' => '2026-02-01 16:30:00', 'status' => 1, 'verify' => 'fingerprint'],
                ],
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.ingested', 2)
            ->assertJsonPath('data.processed', 2);

        // Staging: نبضتان محفوظتان ومعالَجتان.
        $this->assertSame(2, AttendanceLog::where('device_id', $device->id)->count());
        $this->assertSame(2, AttendanceLog::where('status', 'processed')->count());

        // التكامل: سجل حضور واحد بمصدر البصمة، خروج محتسب.
        $record = AttendanceRecord::where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('biometric', $record->source);
        $this->assertNotNull($record->check_in);
        $this->assertNotNull($record->check_out);
        $this->assertSame(510, $record->worked_minutes); // 08:00 → 16:30

        $this->assertDatabaseHas('audit_logs', ['action' => 'biometric_webhook_received']);
    }

    public function test_resend_is_idempotent(): void
    {
        $device = $this->device();
        Employee::factory()->create(['biometric_user_id' => 'U1001']);
        $payload = ['records' => [['pin' => 'U1001', 'time' => '2026-02-01 08:00:00', 'status' => 0]]];

        $this->withHeaders(['X-Device-Secret' => 'super-secret-key'])
            ->postJson("/api/biometric/devices/{$device->id}/webhook", $payload)
            ->assertStatus(202)->assertJsonPath('data.ingested', 1);

        // إعادة الإرسال نفسه → تكرار، لا سجل جديد.
        $this->withHeaders(['X-Device-Secret' => 'super-secret-key'])
            ->postJson("/api/biometric/devices/{$device->id}/webhook", $payload)
            ->assertStatus(202)
            ->assertJsonPath('data.ingested', 0)
            ->assertJsonPath('data.duplicates', 1);

        $this->assertSame(1, AttendanceLog::count());
    }

    public function test_unmatched_biometric_user_is_flagged_not_lost(): void
    {
        $device = $this->device();
        // لا يوجد موظف بهذا المعرّف.

        $this->withHeaders(['X-Device-Secret' => 'super-secret-key'])
            ->postJson("/api/biometric/devices/{$device->id}/webhook", [
                'records' => [['pin' => 'GHOST', 'time' => '2026-02-01 08:00:00', 'status' => 0]],
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.unmatched', 1);

        // النبضة محفوظة (لا فقدان) لكن غير معالَجة.
        $this->assertDatabaseHas('attendance_logs', ['biometric_user_id' => 'GHOST', 'status' => 'unmatched']);
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_webhook_rejects_wrong_secret(): void
    {
        $device = $this->device();

        $this->withHeaders(['X-Device-Secret' => 'wrong'])
            ->postJson("/api/biometric/devices/{$device->id}/webhook", ['records' => []])
            ->assertStatus(401);

        // بلا ترويسة إطلاقاً.
        $this->postJson("/api/biometric/devices/{$device->id}/webhook", ['records' => []])
            ->assertStatus(401);

        $this->assertSame(0, AttendanceLog::count());
    }

    public function test_webhook_rejects_inactive_device(): void
    {
        $device = $this->device(['is_active' => false]);

        $this->withHeaders(['X-Device-Secret' => 'super-secret-key'])
            ->postJson("/api/biometric/devices/{$device->id}/webhook", ['records' => []])
            ->assertStatus(403);
    }

    public function test_attlog_text_payload_is_parsed(): void
    {
        $device = $this->device();
        $employee = Employee::factory()->create(['biometric_user_id' => 'U77']);

        $this->withHeaders(['X-Device-Secret' => 'super-secret-key'])
            ->postJson("/api/biometric/devices/{$device->id}/webhook", [
                'attlog' => "U77\t2026-02-02 09:00:00\t0\t1\nU77\t2026-02-02 17:00:00\t1\t1",
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.ingested', 2);

        $record = AttendanceRecord::where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('biometric', $record->source);
        $this->assertSame(480, $record->worked_minutes); // 09:00 → 17:00
    }
}
