<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Seeders\RbacSeeder;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * تحقّق أن دور «الموارد البشرية» المسنَد من الـ Seeder (دون حقن صلاحيات مباشر) يفتح بوابة
 * HR في الإنتاج: يصل إلى إدارة الموظفين، ويُمنع من إدارة النظام. يثبت إصلاح الدور الفارغ.
 */
class HrPortalAccessTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function seededHr(): User
    {
        $this->seed(RbacSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('hr');

        return $user;
    }

    public function test_seeded_hr_can_reach_employee_management(): void
    {
        $user = $this->seededHr();

        // نفس النداء الذي يستخدمه اكتشاف قدرة HR في الواجهة (يحرسه employees.view).
        $this->actingAsToken($user)->getJson('/api/employees?per_page=1')->assertOk();
    }

    public function test_user_without_hr_role_is_denied_employee_management(): void
    {
        $this->seed(RbacSeeder::class);
        $user = User::factory()->create(); // بلا أدوار

        $this->actingAsToken($user)->getJson('/api/employees?per_page=1')
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'FORBIDDEN');
    }

    public function test_seeded_hr_is_blocked_from_system_administration(): void
    {
        $user = $this->seededHr();

        // إدارة الأدوار محروسة بـ roles.manage — ليست ضمن صلاحيات HR.
        $this->actingAsToken($user)->getJson('/api/roles')
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'FORBIDDEN');
    }
}
