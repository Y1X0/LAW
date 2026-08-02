<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * يتحقق من محرّك RBAC: فحص الصلاحيات/الأدوار، تكامل Gate، والـ middleware.
 */
class AuthorizationTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_user_has_permission_through_role(): void
    {
        $user = $this->userWithPermissions(['cases.view']);

        $this->assertTrue($user->hasPermission('cases.view'));
        $this->assertFalse($user->hasPermission('cases.delete'));
    }

    public function test_has_role_checks_assigned_roles(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'lawyer', 'display_name' => 'محامٍ']);
        $user->assignRole($role);

        $this->assertTrue($user->hasRole('lawyer'));
        $this->assertTrue($user->hasRole(['admin', 'lawyer']));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_gate_grants_ability_from_permission(): void
    {
        $user = $this->userWithPermissions(['reports.finance']);

        $this->assertTrue($user->can('reports.finance'));
        $this->assertFalse($user->can('reports.hr'));
    }

    public function test_permission_middleware_allows_authorized_user(): void
    {
        $user = $this->userWithPermissions(['roles.manage']);

        $this->actingAsToken($user)->getJson('/api/roles')->assertOk();
    }

    public function test_permission_middleware_blocks_unauthorized_user_and_audits(): void
    {
        $user = User::factory()->create(); // بلا صلاحيات

        $this->actingAsToken($user)->getJson('/api/roles')
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'FORBIDDEN');

        $this->assertDatabaseHas('audit_logs', ['action' => 'permission_denied', 'user_id' => $user->id]);
    }

    public function test_rbac_routes_require_authentication(): void
    {
        $this->getJson('/api/roles')
            ->assertStatus(401)
            ->assertJsonPath('errors.code', 'UNAUTHENTICATED');
    }

    public function test_assign_and_remove_role_updates_permissions(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'accountant', 'display_name' => 'المالية']);
        $perm = Permission::create(['name' => 'finance.reports', 'module' => 'finance']);
        $role->permissions()->attach($perm->id);

        $user->assignRole($role);
        $this->assertTrue($user->hasPermission('finance.reports'));

        $user->removeRole($role);
        $this->assertFalse($user->hasPermission('finance.reports'));
    }

    public function test_permission_cache_is_invalidated_after_role_change(): void
    {
        $user = User::factory()->create();
        // أول استدعاء يبني الذاكرة المؤقتة (فارغة)
        $this->assertFalse($user->hasPermission('cases.view'));

        $role = Role::create(['name' => 'viewer', 'display_name' => 'مطّلع']);
        $role->permissions()->attach(Permission::create(['name' => 'cases.view', 'module' => 'cases'])->id);
        $user->assignRole($role);

        // بعد الإسناد يجب أن تعكس الذاكرة المؤقتة الصلاحية الجديدة (لا قيمة قديمة)
        $this->assertTrue($user->hasPermission('cases.view'));
    }
}
