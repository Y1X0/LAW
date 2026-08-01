<?php

namespace Tests\Feature\Rbac;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * إدارة الأدوار وربط الصلاحيات عبر الـ API (محميّة بصلاحية roles.manage).
 */
class RoleManagementTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_can_list_and_create_roles(): void
    {
        $admin = $this->userWithPermissions(['roles.manage']);

        $this->actingAsToken($admin)->postJson('/api/roles', [
            'name' => 'auditor', 'display_name' => 'مدقّق',
        ])->assertCreated()->assertJsonPath('data.name', 'auditor');

        $this->assertDatabaseHas('roles', ['name' => 'auditor']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role_created']);
    }

    public function test_can_update_role_display_name(): void
    {
        $admin = $this->userWithPermissions(['roles.manage']);
        $role = Role::create(['name' => 'reviewer', 'display_name' => 'قديم']);

        $this->actingAsToken($admin)->putJson("/api/roles/{$role->id}", ['display_name' => 'مُراجِع'])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'مُراجِع');

        $this->assertDatabaseHas('audit_logs', ['action' => 'role_updated']);
    }

    public function test_can_sync_permissions_to_role(): void
    {
        $admin = $this->userWithPermissions(['roles.manage']);
        $role = Role::create(['name' => 'clerk', 'display_name' => 'كاتب']);
        $p1 = Permission::create(['name' => 'cases.view', 'module' => 'cases']);
        $p2 = Permission::create(['name' => 'clients.view', 'module' => 'clients']);

        $this->actingAsToken($admin)
            ->putJson("/api/roles/{$role->id}/permissions", ['permissions' => [$p1->id, $p2->id]])
            ->assertOk();

        $this->assertCount(2, $role->fresh()->permissions);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role_permissions_synced']);
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $admin = $this->userWithPermissions(['roles.manage']);
        $system = Role::create(['name' => 'admin', 'display_name' => 'المدير', 'is_system' => true]);

        $this->actingAsToken($admin)->deleteJson("/api/roles/{$system->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'ROLE_PROTECTED');

        $this->assertDatabaseHas('roles', ['id' => $system->id]);
    }

    public function test_non_system_role_can_be_deleted(): void
    {
        $admin = $this->userWithPermissions(['roles.manage']);
        $role = Role::create(['name' => 'temp', 'display_name' => 'مؤقت']);

        $this->actingAsToken($admin)->deleteJson("/api/roles/{$role->id}")->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_create_role_validates_unique_name(): void
    {
        $admin = $this->userWithPermissions(['roles.manage']);
        Role::create(['name' => 'dup', 'display_name' => 'مكرر']);

        $this->actingAsToken($admin)->postJson('/api/roles', ['name' => 'dup', 'display_name' => 'x'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    /**
     * SEC-1: حامل roles.manage (وليس مالك منصّة) لا يستطيع تصعيد صلاحياته بمنح
     * users.manage لدور — وإلّا لأصبح مديراً كاملاً عبر Gate::before، خلافاً لـ ADR-006.
     */
    public function test_roles_manage_holder_cannot_escalate_by_granting_users_manage(): void
    {
        $actor = $this->userWithPermissions(['roles.manage']); // لا يملك users.manage
        $role = Role::create(['name' => 'clerk', 'display_name' => 'كاتب']);
        $usersManage = Permission::firstOrCreate(['name' => 'users.manage'], ['module' => 'users']);

        $this->actingAsToken($actor)
            ->putJson("/api/roles/{$role->id}/permissions", ['permissions' => [$usersManage->id]])
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'FORBIDDEN');

        $this->assertFalse(
            $role->fresh()->permissions->contains('name', 'users.manage'),
            'users.manage يجب ألا تُمنح للدور عبر تصعيد.'
        );
    }

    /** SEC-1: مالك منصّة يملك users.manage فعلاً يستطيع منحها لدور آخر (سلوك مشروع). */
    public function test_platform_owner_can_grant_users_manage(): void
    {
        $owner = $this->userWithPermissions(['roles.manage', 'users.manage']);
        $role = Role::create(['name' => 'co-admin', 'display_name' => 'مدير مساعد']);
        $usersManage = Permission::firstOrCreate(['name' => 'users.manage'], ['module' => 'users']);

        $this->actingAsToken($owner)
            ->putJson("/api/roles/{$role->id}/permissions", ['permissions' => [$usersManage->id]])
            ->assertOk();

        $this->assertTrue(
            $role->fresh()->permissions->contains('name', 'users.manage'),
            'مالك المنصّة يجب أن يستطيع منح users.manage.'
        );
    }

    /**
     * SEC-1 (عدم انحدار): حامل roles.manage يبقى قادراً على مزامنة الصلاحيات العادية —
     * الحارس يخصّ users.manage فقط ولا يعيق إدارة الأدوار المشروعة.
     */
    public function test_roles_manage_holder_can_still_sync_non_admin_permissions(): void
    {
        $actor = $this->userWithPermissions(['roles.manage']);
        $role = Role::create(['name' => 'clerk2', 'display_name' => 'كاتب']);
        $p = Permission::firstOrCreate(['name' => 'cases.view', 'module' => 'cases']);

        $this->actingAsToken($actor)
            ->putJson("/api/roles/{$role->id}/permissions", ['permissions' => [$p->id]])
            ->assertOk();

        $this->assertTrue($role->fresh()->permissions->contains('name', 'cases.view'));
    }
}
