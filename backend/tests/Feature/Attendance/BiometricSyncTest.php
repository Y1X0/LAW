<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Modules\Attendance\Biometric\BiometricAdapterFactory;
use Modules\Attendance\Biometric\Contracts\BiometricAdapter;
use Modules\Attendance\Biometric\PunchData;
use Modules\Attendance\Jobs\SyncBiometricDeviceJob;
use Modules\Attendance\Models\BiometricDevice;
use Modules\Attendance\Services\BiometricIngestionService;
use Modules\HR\Models\Employee;
use Tests\TestCase;

/**
 * مزامنة Pull/التسوية (Issue #16): مهمة المزامنة تسحب عبر المحوّل، تستوعب (منع التكرار)،
 * وتحدّث last_sync_at + status. نستبدل المصنع بمحوّل مزيّف لعزل منطق المزامنة عن الجهاز.
 */
class BiometricSyncTest extends TestCase
{
    use RefreshDatabase;

    private function bindFakeAdapterReturning(array $punches, string $status = 'online'): void
    {
        $fake = new class($punches, $status) implements BiometricAdapter
        {
            public function __construct(private array $punches, private string $status) {}

            public function connect(): bool
            {
                return true;
            }

            public function fetchLogs(?Carbon $since): array
            {
                return $this->punches;
            }

            public function parseWebhook(array $payload): array
            {
                return [];
            }

            public function normalize(array $raw): PunchData
            {
                return new PunchData('', Carbon::now());
            }

            public function enrollUser(Employee $employee): bool
            {
                return true;
            }

            public function deleteUser(string $biometricUserId): bool
            {
                return true;
            }

            public function getDeviceStatus(): string
            {
                return $this->status;
            }
        };

        $factory = new class($fake) extends BiometricAdapterFactory
        {
            public function __construct(private BiometricAdapter $fake) {}

            public function make(BiometricDevice $device): BiometricAdapter
            {
                return $this->fake;
            }
        };

        $this->app->instance(BiometricAdapterFactory::class, $factory);
    }

    public function test_sync_job_pulls_ingests_and_updates_device_state(): void
    {
        $device = BiometricDevice::create(['name' => 'جهاز', 'vendor' => 'zkteco', 'api_mode' => 'pull', 'is_active' => true]);
        $employee = Employee::factory()->create(['biometric_user_id' => '1001']);

        $this->bindFakeAdapterReturning([
            new PunchData('1001', Carbon::parse('2026-01-06 08:00:00'), 'in'),
            new PunchData('1001', Carbon::parse('2026-01-06 08:00:00'), 'in'), // مكرر — يُتجاهل
        ]);

        (new SyncBiometricDeviceJob($device->id))->handle(
            app(BiometricAdapterFactory::class),
            app(BiometricIngestionService::class),
        );

        $this->assertDatabaseCount('attendance_logs', 1); // التكرار مُنع
        $this->assertDatabaseHas('attendance_records', ['employee_id' => $employee->id, 'source' => 'biometric']);

        $device->refresh();
        $this->assertNotNull($device->last_sync_at);
        $this->assertSame('online', $device->status);
    }

    public function test_command_dispatches_sync_for_active_pull_devices_only(): void
    {
        Queue::fake();
        BiometricDevice::create(['name' => 'نشط Pull', 'vendor' => 'zkteco', 'api_mode' => 'pull', 'is_active' => true]);
        BiometricDevice::create(['name' => 'معطّل', 'vendor' => 'zkteco', 'api_mode' => 'pull', 'is_active' => false]);
        // جهاز Push نشط — يدفع سجلّاته إلى الـ API فلا يُسحَب (يُستثنى من المزامنة).
        BiometricDevice::create(['name' => 'نشط Push', 'vendor' => 'zkteco', 'api_mode' => 'push', 'is_active' => true]);

        $this->artisan('biometric:sync')->assertSuccessful();

        // جهاز واحد فقط (النشط بوضع Pull).
        Queue::assertPushed(SyncBiometricDeviceJob::class, 1);
    }
}
