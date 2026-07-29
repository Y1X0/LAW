<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Attendance\Jobs\SyncBiometricDeviceJob;
use Modules\Attendance\Models\BiometricDevice;
use Modules\Core\Models\AuditLog;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * إدارة أجهزة البصمة عبر الـ API (Issue #16): التسجيل، إخفاء التوكن،
 * فرض الصلاحية، إطلاق المزامنة، والتدقيق.
 */
class BiometricDeviceTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_register_device_returns_token_once_and_hides_it_afterwards(): void
    {
        $actor = $this->userWithPermissions(['attendance.devices']);

        $this->actingAsToken($actor)->postJson('/api/biometric/devices', [
            'name' => 'جهاز البوابة', 'vendor' => 'zkteco', 'api_mode' => 'push',
        ])->assertCreated()
            ->assertJsonPath('data.vendor', 'zkteco')
            ->assertJsonStructure(['data' => ['id', 'auth_token']]);

        $device = BiometricDevice::first();
        $this->assertNotNull($device->auth_token); // مخزّن (مشفّراً)

        // القائمة لا تكشف التوكن أبداً
        $this->actingAsToken($actor)->getJson('/api/biometric/devices')
            ->assertOk()
            ->assertJsonMissing(['auth_token' => $device->auth_token]);

        $this->assertTrue(AuditLog::where('action', 'biometric_device_registered')->exists());
    }

    public function test_device_management_requires_permission(): void
    {
        $actor = $this->userWithPermissions([]); // بلا صلاحيات

        $this->actingAsToken($actor)->getJson('/api/biometric/devices')->assertStatus(403);
        $this->actingAsToken($actor)->postJson('/api/biometric/devices', [
            'name' => 'x', 'vendor' => 'zkteco', 'api_mode' => 'push',
        ])->assertStatus(403);
    }

    public function test_sync_endpoint_dispatches_job(): void
    {
        Queue::fake();
        $actor = $this->userWithPermissions(['attendance.devices']);
        $device = BiometricDevice::create(['name' => 'جهاز', 'vendor' => 'zkteco', 'api_mode' => 'pull', 'is_active' => true]);

        $this->actingAsToken($actor)->postJson("/api/biometric/devices/{$device->id}/sync")
            ->assertOk()->assertJsonPath('data.dispatched', true);

        Queue::assertPushed(SyncBiometricDeviceJob::class);
        $this->assertTrue(AuditLog::where('action', 'biometric_sync_triggered')->exists());
    }

    public function test_enroll_records_audit(): void
    {
        $actor = $this->userWithPermissions(['attendance.devices']);
        $device = BiometricDevice::create(['name' => 'جهاز', 'vendor' => 'zkteco', 'api_mode' => 'push', 'is_active' => true]);
        $employee = Employee::factory()->create(['biometric_user_id' => '1001']);

        $this->actingAsToken($actor)->postJson("/api/biometric/devices/{$device->id}/enroll", [
            'employee_id' => $employee->id,
        ])->assertOk()->assertJsonPath('data.employee_id', $employee->id);

        $this->assertTrue(AuditLog::where('action', 'biometric_user_enrolled')->exists());
    }
}
