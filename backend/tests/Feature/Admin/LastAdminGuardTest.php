<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * حارس «آخر مدير» (ADMIN-3): يمنع عمليات RBAC القائمة من إسقاط قدرة users.manage
 * عن آخر مدير فعّال. إضافي بحت — لا يغيّر سلوك المسارات في الحالات المشروعة.
 */
class LastAdminGuardTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** ينشئ دوراً يمنح صلاحية بعينها (مع اسم فريد) ويعيده. */
    private function roleWith(array $permissions, bool $system = false): Role
    {
        $role = Role::create(['name' => 'r-'.Str::random(6), 'display_name' => 'اختبار', 'is_system' => $system]);
        foreach ($permissions as $name) {
            $perm = Permission::firstOrCreate(['name' => $name], ['module' => Str::before($name, '.')]);
            $role->permissions()->attach($perm->id);
        }

        return $role;
    }

    // ---------- إزالة دور مدير عن مستخدم ----------

    public function test_cannot_remove_admin_role_from_last_admin(): void
    {
        $adminRole = $this->roleWith(['users.manage']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole($adminRole); // المدير الوحيد

        $this->actingAsToken($admin)
            ->deleteJson("/api/users/{$admin->id}/roles/{$adminRole->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'LAST_ADMIN_PROTECTED');

        $this->assertTrue($admin->fresh()->hasPermission('users.manage'));
    }

    public function test_can_remove_admin_role_when_another_admin_exists(): void
    {
        $adminRole = $this->roleWith(['users.manage']);
        $admin = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);
        $admin->assignRole($adminRole);
        $other->assignRole($adminRole); // مديران فعّالان

        $this->actingAsToken($admin)
            ->deleteJson("/api/users/{$other->id}/roles/{$adminRole->id}")
            ->assertOk();

        $this->assertFalse($other->fresh()->hasPermission('users.manage'));
    }

    // ---------- حذف دور مدير ----------

    public function test_cannot_delete_role_that_is_last_admin_source(): void
    {
        $manager = $this->userWithPermissions(['roles.manage']); // يدير الأدوار، وليس مديراً للمستخدمين
        $adminRole = $this->roleWith(['users.manage']); // غير نظامي
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole($adminRole);

        $this->actingAsToken($manager)
            ->deleteJson("/api/roles/{$adminRole->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'LAST_ADMIN_PROTECTED');

        $this->assertDatabaseHas('roles', ['id' => $adminRole->id]);
    }

    public function test_can_delete_admin_role_when_another_admin_source_exists(): void
    {
        $manager = $this->userWithPermissions(['roles.manage']);
        $roleA = $this->roleWith(['users.manage']);
        $roleB = $this->roleWith(['users.manage']);
        User::factory()->create(['status' => 'active'])->assignRole($roleB); // مدير عبر دور آخر

        $this->actingAsToken($manager)
            ->deleteJson("/api/roles/{$roleA->id}")
            ->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $roleA->id]);
    }

    // ---------- تجريد users.manage عبر مزامنة الصلاحيات ----------

    public function test_cannot_strip_users_manage_from_last_admin_role(): void
    {
        $manager = $this->userWithPermissions(['roles.manage']);
        $rolesManage = Permission::firstOrCreate(['name' => 'roles.manage'], ['module' => 'roles']);
        $adminRole = $this->roleWith(['users.manage']);
        User::factory()->create(['status' => 'active'])->assignRole($adminRole);

        // مزامنة تُبقي roles.manage فقط وتُسقط users.manage.
        $this->actingAsToken($manager)
            ->putJson("/api/roles/{$adminRole->id}/permissions", ['permissions' => [$rolesManage->id]])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'LAST_ADMIN_PROTECTED');

        $this->assertTrue($adminRole->fresh()->permissions()->where('name', 'users.manage')->exists());
    }

    public function test_can_strip_users_manage_when_another_admin_role_covers_it(): void
    {
        $manager = $this->userWithPermissions(['roles.manage']);
        $roleA = $this->roleWith(['users.manage']);
        $roleB = $this->roleWith(['users.manage']);
        User::factory()->create(['status' => 'active'])->assignRole($roleB); // تغطية بديلة

        $this->actingAsToken($manager)
            ->putJson("/api/roles/{$roleA->id}/permissions", ['permissions' => []])
            ->assertOk();

        $this->assertFalse($roleA->fresh()->permissions()->where('name', 'users.manage')->exists());
    }
}
