<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Setting;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * إعدادات المنصّة العامّة (ADMIN-5) — نقاط محميّة بصلاحية settings.manage.
 */
class SettingsManagementTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_lists_global_settings_grouped(): void
    {
        $admin = $this->userWithPermissions(['settings.manage']);
        Setting::create(['branch_id' => null, 'group' => 'general', 'key' => 'name', 'value' => 'مكتبي']);

        $this->actingAsToken($admin)->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.general.0.key', 'name');
    }

    public function test_upserts_settings_idempotently(): void
    {
        $admin = $this->userWithPermissions(['settings.manage']);

        $payload = ['settings' => [['group' => 'general', 'key' => 'name', 'value' => 'مكتب الحق']]];

        $this->actingAsToken($admin)->putJson('/api/admin/settings', $payload)->assertOk();
        // تكرار نفس الطلب لا يُنشئ صفّاً جديداً (updateOrCreate).
        $this->actingAsToken($admin)->putJson('/api/admin/settings', $payload)->assertOk();

        $this->assertSame(1, Setting::where('group', 'general')->where('key', 'name')->count());
        $this->assertDatabaseHas('settings', ['group' => 'general', 'key' => 'name']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings_updated']);
    }

    public function test_update_validates_payload(): void
    {
        $admin = $this->userWithPermissions(['settings.manage']);

        $this->actingAsToken($admin)->putJson('/api/admin/settings', [
            'settings' => [['key' => 'name', 'value' => 'x']], // group مفقود
        ])->assertStatus(422)->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/admin/settings')->assertUnauthorized();
    }

    public function test_forbidden_without_settings_manage_permission(): void
    {
        $noPerm = $this->userWithPermissions(['audit.view']);

        $this->actingAsToken($noPerm)->getJson('/api/admin/settings')->assertForbidden();
        $this->actingAsToken($noPerm)->putJson('/api/admin/settings', ['settings' => []])->assertForbidden();
    }
}
