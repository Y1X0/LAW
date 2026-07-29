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
}
