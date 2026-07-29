<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Role;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * إسناد/إزالة أدوار المستخدمين عبر الـ API (محميّ بصلاحية users.manage).
 */
class UserRoleAssignmentTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_admin_can_assign_role_to_user(): void
    {
        $admin = $this->userWithPermissions(['users.manage']);
        $target = User::factory()->create();
        $role = Role::create(['name' => 'lawyer', 'display_name' => 'محامٍ']);

        $this->actingAsToken($admin)->postJson("/api/users/{$target->id}/roles", ['role_id' => $role->id])
            ->assertCreated();

        $this->assertTrue($target->fresh()->hasRole('lawyer'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_role_assigned', 'auditable_id' => $target->id]);
    }

    public function test_role_can_be_branch_scoped(): void
    {
        $admin = $this->userWithPermissions(['users.manage']);
        $target = User::factory()->create();
        $role = Role::create(['name' => 'hr', 'display_name' => 'موارد']);
        $branch = Branch::create(['name' => 'جدة', 'code' => 'JED']);

        $this->actingAsToken($admin)->postJson("/api/users/{$target->id}/roles", [
            'role_id' => $role->id, 'branch_id' => $branch->id,
        ])->assertCreated();

        $this->assertDatabaseHas('user_role', [
            'user_id' => $target->id, 'role_id' => $role->id, 'branch_id' => $branch->id,
        ]);
    }

    public function test_admin_can_remove_role_from_user(): void
    {
        $admin = $this->userWithPermissions(['users.manage']);
        $target = User::factory()->create();
        $role = Role::create(['name' => 'temp', 'display_name' => 'مؤقت']);
        $target->assignRole($role);

        $this->actingAsToken($admin)->deleteJson("/api/users/{$target->id}/roles/{$role->id}")->assertOk();

        $this->assertFalse($target->fresh()->hasRole('temp'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_role_removed']);
    }

    public function test_assignment_requires_users_manage_permission(): void
    {
        $user = $this->userWithPermissions(['roles.manage']); // صلاحية أخرى لا تكفي
        $target = User::factory()->create();
        $role = Role::create(['name' => 'x', 'display_name' => 'x']);

        $this->actingAsToken($user)->postJson("/api/users/{$target->id}/roles", ['role_id' => $role->id])
            ->assertStatus(403);
    }
}
