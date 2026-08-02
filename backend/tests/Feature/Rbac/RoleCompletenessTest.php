<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\Core\Seeders\RbacSeeder;
use Modules\HR\Models\Employee;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * يثبت أن كل دور نظامي — بما فيها التي كانت فارغة (secretary/accountant/marketing)
 * والمحامي المنفرد — يصل فعليًا إلى بوابة عاملة عبر الـAPI، بلا 403 في الخدمة الذاتية.
 */
class RoleCompletenessTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** مستخدم بدور مسنَد من الـSeeder مربوط بسجلّ موظف (للخدمة الذاتية). */
    private function seededUserWithRole(string $roleName): User
    {
        $this->seed(RbacSeeder::class);
        $branch = Branch::firstOrCreate(['code' => 'HQ'], ['name' => 'المكتب الرئيسي']);
        $dept = Department::firstOrCreate(['branch_id' => $branch->id, 'name' => 'الإدارة']);

        $user = User::factory()->create();
        $user->assignRole($roleName);
        Employee::factory()->create(['user_id' => $user->id, 'branch_id' => $branch->id, 'department_id' => $dept->id]);

        return $user;
    }

    public static function staffRoles(): array
    {
        return [['employee'], ['secretary'], ['accountant'], ['marketing'], ['lawyer'], ['hr']];
    }

    /** كل دور موظّف يفتح لوحته الذاتية (dashboard.view_own) — لا قفل بعد اليوم. */
    #[DataProvider('staffRoles')]
    public function test_every_staff_role_opens_self_service_dashboard(string $roleName): void
    {
        $user = $this->seededUserWithRole($roleName);

        $this->actingAsToken($user)->getJson('/api/me/dashboard')->assertOk();
    }

    /** المحامي المنفرد (بلا دور «موظف») يفتح إجازاته وكشفه وملفه — إصلاح فخّ F2. */
    public function test_lawyer_alone_reaches_self_service_pages(): void
    {
        $user = $this->seededUserWithRole('lawyer');

        $this->actingAsToken($user)->getJson('/api/me/leave/requests')->assertOk();
        $this->actingAsToken($user)->getJson('/api/me/payslips')->assertOk();
        $this->actingAsToken($user)->getJson('/api/me/profile')->assertOk();
    }
}
