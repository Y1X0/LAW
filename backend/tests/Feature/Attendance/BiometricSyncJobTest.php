<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Modules\Attendance\Adapters\BiometricAdapter;
use Modules\Attendance\Adapters\BiometricAdapterManager;
use Modules\Attendance\Jobs\PullBiometricDeviceJob;
use Modules\Attendance\Models\AttendanceLog;
use Modules\Attendance\Models\BiometricDevice;
use Modules\Attendance\Services\BiometricSyncService;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class BiometricSyncJobTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_command_dispatches_pull_job_for_active_pull_devices_only(): void
    {
        Queue::fake();

        BiometricDevice::create(['name' => 'A', 'vendor' => 'zkteco', 'api_mode' => 'pull', 'secret' => 'device-secret-1', 'is_active' => true]);
        BiometricDevice::create(['name' => 'B', 'vendor' => 'zkteco', 'api_mode' => 'push', 'secret' => 'device-secret-2', 'is_active' => true]);
        BiometricDevice::create(['name' => 'C', 'vendor' => 'zkteco', 'api_mode' => 'pull', 'secret' => 'device-secret-3', 'is_active' => false]);

        $this->artisan('attendance:sync-biometric')->assertSuccessful();

        // جهاز واحد فقط (نشط + pull) يستحق مهمة سحب.
        Queue::assertPushed(PullBiometricDeviceJob::class, 1);
    }

    public function test_pull_job_ingests_and_processes(): void
    {
        $adapter = new class implements BiometricAdapter
        {
            public function vendor(): string
            {
                return 'acme';
            }

            public function connect(BiometricDevice $device): bool
            {
                return true;
            }

            public function enrollUser(BiometricDevice $device, array $user): bool
            {
                return true;
            }

            public function fetchLogs(BiometricDevice $device, ?Carbon $since): array
            {
                return [['pin' => 'J9', 'time' => '2026-04-01 08:00:00', 'type' => 'check_in']];
            }

            public function parseWebhook(array $payload): array
            {
                return [];
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

        Employee::factory()->create(['biometric_user_id' => 'J9']);
        $device = BiometricDevice::create(['name' => 'Acme', 'vendor' => 'acme', 'api_mode' => 'pull', 'secret' => 'device-secret-1']);

        (new PullBiometricDeviceJob($device))->handle(app(BiometricSyncService::class));

        $this->assertSame(1, AttendanceLog::where('status', 'processed')->count());
        $this->assertNotNull($device->fresh()->last_sync_at);
    }

    public function test_pull_job_declares_retry_policy(): void
    {
        $device = BiometricDevice::create(['name' => 'A', 'vendor' => 'zkteco', 'api_mode' => 'pull', 'secret' => 'device-secret-1']);
        $job = new PullBiometricDeviceJob($device);

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 60], $job->backoff());
    }
}
