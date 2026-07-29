<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Models\BiometricDevice;
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
