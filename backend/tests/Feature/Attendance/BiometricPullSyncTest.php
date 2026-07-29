<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Attendance\Adapters\BiometricAdapter;
use Modules\Attendance\Adapters\BiometricAdapterManager;
use Modules\Attendance\Models\AttendanceLog;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Models\BiometricDevice;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class BiometricPullSyncTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** محوّل وهمي يحاكي طبقة النقل (Pull) بسجلات جاهزة. */
    private function registerFakeAdapter(array $rawRecords): void
    {
        $adapter = new class($rawRecords) implements BiometricAdapter
        {
            public function __construct(private array $records) {}

            public function vendor(): string
            {
                return 'acme';
            }

            public function fetchLogs(BiometricDevice $device, ?Carbon $since): array
            {
                return $this->records;
            }

            public function parseWebhook(array $payload): array
            {
                return $payload['records'] ?? [];
            }

            public function normalize(array $raw): array
            {
                return [
                    'biometric_user_id' => (string) $raw['pin'],
                    'punch_time' => Carbon::parse($raw['time']),
                    'punch_type' => $raw['type'] ?? 'unknown',
                    'verify_mode' => null,
                    'raw_payload' => $raw,
                ];
            }
        };

        app(BiometricAdapterManager::class)->extend('acme', fn () => $adapter);
    }

    public function test_pull_ingests_and_processes_fetched_logs(): void
    {
        $this->registerFakeAdapter([
            ['pin' => 'E5', 'time' => '2026-03-01 08:00:00', 'type' => 'check_in'],
            ['pin' => 'E5', 'time' => '2026-03-01 16:00:00', 'type' => 'check_out'],
        ]);

        $admin = $this->userWithPermissions(['attendance.devices']);
        $employee = Employee::factory()->create(['biometric_user_id' => 'E5']);
        $device = BiometricDevice::create([
            'name' => 'Acme', 'vendor' => 'acme', 'api_mode' => 'pull', 'secret' => 'device-secret-1',
        ]);

        $this->actingAsToken($admin)->postJson("/api/biometric/devices/{$device->id}/sync")
            ->assertOk()
            ->assertJsonPath('data.ingested', 2)
            ->assertJsonPath('data.processed', 2);

        $this->assertSame(2, AttendanceLog::where('device_id', $device->id)->where('status', 'processed')->count());

        $record = AttendanceRecord::where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('biometric', $record->source);
        $this->assertSame(480, $record->worked_minutes);
        $this->assertNotNull($device->fresh()->last_sync_at);
    }

    public function test_pull_is_idempotent_across_runs(): void
    {
        $this->registerFakeAdapter([
            ['pin' => 'E5', 'time' => '2026-03-01 08:00:00', 'type' => 'check_in'],
        ]);

        $admin = $this->userWithPermissions(['attendance.devices']);
        Employee::factory()->create(['biometric_user_id' => 'E5']);
        $device = BiometricDevice::create([
            'name' => 'Acme', 'vendor' => 'acme', 'api_mode' => 'pull', 'secret' => 'device-secret-1',
        ]);

        $this->actingAsToken($admin)->postJson("/api/biometric/devices/{$device->id}/sync")
            ->assertOk()->assertJsonPath('data.ingested', 1);

        // سحب ثانٍ لنفس السجل → لا تكرار في Staging.
        $this->actingAsToken($admin)->postJson("/api/biometric/devices/{$device->id}/sync")
            ->assertOk()->assertJsonPath('data.ingested', 0)->assertJsonPath('data.duplicates', 1);

        $this->assertSame(1, AttendanceLog::count());
    }
}
