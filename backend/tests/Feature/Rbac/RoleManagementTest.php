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
}
