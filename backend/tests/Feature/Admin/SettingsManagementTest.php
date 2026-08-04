<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\AuditLog;
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
        Setting::create(['branch_id' => null, 'group' => 'general', 'key' => 'org_name_ar', 'value' => 'مكتبي']);

        $this->actingAsToken($admin)->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.general.0.key', 'org_name_ar');
    }

    public function test_upserts_settings_idempotently(): void
    {
        $admin = $this->userWithPermissions(['settings.manage']);

        $payload = ['settings' => [['group' => 'general', 'key' => 'org_name_ar', 'value' => 'مكتب الحق']]];

        $this->actingAsToken($admin)->putJson('/api/admin/settings', $payload)->assertOk();
        // تكرار نفس الطلب لا يُنشئ صفّاً جديداً (updateOrCreate).
        $this->actingAsToken($admin)->putJson('/api/admin/settings', $payload)->assertOk();

        $this->assertSame(1, Setting::where('group', 'general')->where('key', 'org_name_ar')->count());
        $this->assertDatabaseHas('settings', ['group' => 'general', 'key' => 'org_name_ar']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings_updated']);
    }

    public function test_update_validates_payload(): void
    {
        $admin = $this->userWithPermissions(['settings.manage']);

        $this->actingAsToken($admin)->putJson('/api/admin/settings', [
            'settings' => [['key' => 'org_name_ar', 'value' => 'x']], // group مفقود
        ])->assertStatus(422)->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_upserts_extended_general_and_identity_groups(): void
    {
        $admin = $this->userWithPermissions(['settings.manage']);

        $this->actingAsToken($admin)->putJson('/api/admin/settings', ['settings' => [
            ['group' => 'general', 'key' => 'address', 'value' => 'الرياض - العليا'],
            ['group' => 'general', 'key' => 'website', 'value' => 'https://firm.example'],
            ['group' => 'identity', 'key' => 'commercial_register', 'value' => '1010101010'],
        ]])->assertOk()
            ->assertJsonPath('data.identity.0.key', 'commercial_register')
            ->assertJsonPath('data.identity.0.value', '1010101010');

        // القيمة تُخزَّن عبر cast=array (JSON)؛ نتحقّق عبر النموذج الذي يفكّها.
        $address = Setting::whereNull('branch_id')->where('group', 'general')->where('key', 'address')->first();
        $register = Setting::whereNull('branch_id')->where('group', 'identity')->where('key', 'commercial_register')->first();
        $this->assertSame('الرياض - العليا', $address->value);
        $this->assertSame('1010101010', $register->value);
    }

    public function test_update_rejects_key_in_wrong_group(): void
    {
        $admin = $this->userWithPermissions(['settings.manage']);

        // مفتاح صحيح لكن في مجموعة خاطئة (commercial_register تحت general) يُرفَض.
        $this->actingAsToken($admin)->putJson('/api/admin/settings', [
            'settings' => [['group' => 'general', 'key' => 'commercial_register', 'value' => 'x']],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('settings', ['key' => 'commercial_register']);
    }

    public function test_update_rejects_unknown_key(): void
    {
        $admin = $this->userWithPermissions(['settings.manage']);

        // مفتاح خارج قائمة السماح يُرفَض (يمنع إنشاء مفاتيح عشوائية/الكتابة فوق مفاتيح حسّاسة).
        $this->actingAsToken($admin)->putJson('/api/admin/settings', [
            'settings' => [['group' => 'general', 'key' => 'evil_key', 'value' => 'x']],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('settings', ['key' => 'evil_key']);
    }

    public function test_update_rejects_non_string_value(): void
    {
        $admin = $this->userWithPermissions(['settings.manage']);

        // قيمة غير نصّية (مصفوفة) تُرفَض — يمنع type-confusion والحمولات الكبيرة.
        $this->actingAsToken($admin)->putJson('/api/admin/settings', [
            'settings' => [['group' => 'general', 'key' => 'org_name_ar', 'value' => ['x', 'y']]],
        ])->assertStatus(422);
    }

    public function test_update_logs_values_and_before_image(): void
    {
        $admin = $this->userWithPermissions(['settings.manage']);
        Setting::create(['branch_id' => null, 'group' => 'general', 'key' => 'org_name_ar', 'value' => 'قديم']);

        $this->actingAsToken($admin)->putJson('/api/admin/settings', [
            'settings' => [['group' => 'general', 'key' => 'org_name_ar', 'value' => 'جديد']],
        ])->assertOk();

        $log = AuditLog::where('action', 'settings_updated')->latest('id')->first();
        $this->assertSame('جديد', $log->new_values['general.org_name_ar']);
        $this->assertSame('قديم', $log->old_values['general.org_name_ar']);
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
