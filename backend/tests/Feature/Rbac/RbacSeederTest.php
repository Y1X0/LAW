<?php

namespace Tests\Feature\Rbac;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Seeders\RbacSeeder;
use Tests\TestCase;

class RbacSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_permission_catalog_and_admin_with_all(): void
    {
        $this->seed(RbacSeeder::class);

        $this->assertSame(count(RbacSeeder::PERMISSIONS), Permission::count());

        $admin = Role::where('name', 'admin')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->is_system);
        $this->assertSame(Permission::count(), $admin->permissions()->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RbacSeeder::class);
        $this->seed(RbacSeeder::class);

        $this->assertSame(count(RbacSeeder::PERMISSIONS), Permission::count());
        $this->assertSame(count(RbacSeeder::SYSTEM_ROLES), Role::count());
    }

    public function test_seeder_grants_lawyer_role_its_portal_permissions(): void
    {
        $this->seed(RbacSeeder::class);

        $lawyer = Role::where('name', 'lawyer')->first();
        $this->assertNotNull($lawyer);

        $names = $lawyer->permissions()->pluck('name')->all();
        $this->assertSame(count(RbacSeeder::LAWYER_PERMISSIONS), count($names));
        foreach (RbacSeeder::LAWYER_PERMISSIONS as $permission) {
            $this->assertContains($permission, $names);
        }
        // لا صلاحيات إدارية مسرّبة للدور.
        $this->assertNotContains('users.manage', $names);
        $this->assertNotContains('cases.delete', $names);
    }

    public function test_seeder_grants_employee_role_self_service_permissions(): void
    {
        $this->seed(RbacSeeder::class);

        $employee = Role::where('name', 'employee')->first();
        $this->assertNotNull($employee);

        $names = $employee->permissions()->pluck('name')->all();
        $this->assertSame(count(RbacSeeder::EMPLOYEE_PERMISSIONS), count($names));
        foreach (RbacSeeder::EMPLOYEE_PERMISSIONS as $permission) {
            $this->assertContains($permission, $names);
        }
        // خدمة ذاتية فقط — لا صلاحيات قانونية/إدارية مسرّبة.
        $this->assertNotContains('cases.view_own', $names);
        $this->assertNotContains('users.manage', $names);
    }

    public function test_seeder_grants_hr_role_its_portal_permissions(): void
    {
        $this->seed(RbacSeeder::class);

        $hr = Role::where('name', 'hr')->first();
        $this->assertNotNull($hr);

        $names = $hr->permissions()->pluck('name')->all();
        $this->assertSame(count(RbacSeeder::HR_PERMISSIONS), count($names));
        foreach (RbacSeeder::HR_PERMISSIONS as $permission) {
            $this->assertContains($permission, $names);
        }
        // بوابة HR فقط — لا صلاحيات إدارة نظام مسرّبة.
        $this->assertNotContains('users.manage', $names);
        $this->assertNotContains('roles.manage', $names);
        $this->assertNotContains('settings.manage', $names);
    }

    public function test_hr_role_permissions_are_idempotent(): void
    {
        $this->seed(RbacSeeder::class);
        $this->seed(RbacSeeder::class);

        $hr = Role::where('name', 'hr')->first();
        $this->assertSame(count(RbacSeeder::HR_PERMISSIONS), $hr->permissions()->count());
    }
}
