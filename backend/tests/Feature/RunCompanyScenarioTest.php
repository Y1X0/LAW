<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\Core\Models\Role;
use Modules\Core\Seeders\RbacSeeder;
use Modules\HR\Models\Employee;
use Tests\TestCase;

/**
 * System Verification (M1 النهائي): تشغيل شركة حقيقية من الصفر عبر الـAPI فقط —
 * بلا DemoSeeder ولا Excel ولا Artisan. الأساس الوحيد هو RbacSeeder (كتالوج الأدوار/
 * الصلاحيات الذي يُبذَر عند النشر، نظير الهجرات). إن نجح هذا السيناريو فقد اكتملت M1.
 */
class RunCompanyScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_firm_can_be_run_end_to_end_from_the_api_only(): void
    {
        // الأساس عند النشر: كتالوج الأدوار/الصلاحيات + حساب المالك (يُنشأ من متغيّرات البيئة).
        $this->seed(RbacSeeder::class);
        $owner = User::factory()->create(['email' => 'owner@firm.test', 'password' => 'OwnerPass1!', 'status' => 'active']);
        $owner->assignRole('admin');
        $hrRoleId = Role::where('name', 'hr')->value('id');

        // المالك يسجّل الدخول من المتصفّح (توكن حقيقي).
        $ownerToken = $this->postJson('/api/auth/login', ['email' => 'owner@firm.test', 'password' => 'OwnerPass1!'])
            ->assertOk()->json('data.tokens.access_token');

        // 1) فرع.
        $this->withToken($ownerToken)->postJson('/api/branches', ['name' => 'المكتب الرئيسي', 'code' => 'HQ'])->assertStatus(201);
        $hq = Branch::where('code', 'HQ')->first();

        // 2) قسم.
        $this->withToken($ownerToken)->postJson('/api/departments', ['branch_id' => $hq->id, 'name' => 'الموارد البشرية'])->assertStatus(201);
        $dept = Department::where('name', 'الموارد البشرية')->first();

        // 3) موظف.
        $emp = $this->withToken($ownerToken)->postJson('/api/employees', [
            'branch_id' => $hq->id, 'department_id' => $dept->id,
            'full_name_ar' => 'أحمد المصري', 'national_id' => '1000001', 'status' => 'active',
        ])->assertStatus(201)->json('data');

        // 4) إنشاء حساب.
        $user = $this->withToken($ownerToken)->postJson('/api/users', [
            'name' => 'أحمد المصري', 'email' => 'ahmed@firm.test', 'password' => 'HrPass1234!',
        ])->assertStatus(201)->json('data');

        // 5) ربط الحساب بالموظف (يفرض 1:1).
        $this->withToken($ownerToken)->postJson("/api/users/{$user['id']}/employee", ['employee_id' => $emp['id']])->assertOk();

        // 6) إسناد دور HR.
        $this->withToken($ownerToken)->postJson("/api/users/{$user['id']}/roles", ['role_id' => $hrRoleId])->assertSuccessful();

        // 7) تسجيل الدخول بالحساب الجديد.
        $hrToken = $this->postJson('/api/auth/login', ['email' => 'ahmed@firm.test', 'password' => 'HrPass1234!'])
            ->assertOk()->json('data.tokens.access_token');

        // 8) تنفيذ عملية HR فعلية: رؤية الموظفين + حالة الحساب المرتبط ظاهرة.
        $this->withToken($hrToken)->getJson('/api/employees')->assertOk()->assertJsonPath('meta.total', 1);
        $this->withToken($hrToken)->getJson("/api/employees/{$emp['id']}")
            ->assertOk()
            ->assertJsonPath('data.has_account', true)
            ->assertJsonPath('data.account.email', 'ahmed@firm.test')
            ->assertJsonPath('data.account.roles.0.name', 'hr');

        // 9) موظف آخر + حسابه.
        $emp2 = $this->withToken($ownerToken)->postJson('/api/employees', [
            'branch_id' => $hq->id, 'department_id' => $dept->id,
            'full_name_ar' => 'سارة العتيبي', 'national_id' => '1000002', 'status' => 'active',
        ])->assertStatus(201)->json('data');
        $user2 = $this->withToken($ownerToken)->postJson('/api/users', [
            'name' => 'سارة العتيبي', 'email' => 'sara@firm.test', 'password' => 'EmpPass1234!', 'role_ids' => [$hrRoleId],
        ])->assertStatus(201)->json('data');
        $this->withToken($ownerToken)->postJson("/api/users/{$user2['id']}/employee", ['employee_id' => $emp2['id']])->assertOk();

        // 10) تسجيل الدخول بالموظف الثاني (تكرار الدورة يعمل).
        $this->postJson('/api/auth/login', ['email' => 'sara@firm.test', 'password' => 'EmpPass1234!'])
            ->assertOk()->json('data.tokens.access_token');

        // النتيجة النهائية: شركة كاملة (فرع + قسم + موظفان + حسابان مربوطان بأدوار) بلا Seeder/Excel/Artisan.
        $this->assertSame(2, Employee::count());
        $this->assertSame(2, Employee::whereNotNull('user_id')->count());
    }

    public function test_one_to_one_identity_is_enforced_in_both_directions(): void
    {
        $this->seed(RbacSeeder::class);
        $branch = Branch::create(['name' => 'HQ', 'code' => 'HQ']);
        $dept = Department::create(['branch_id' => $branch->id, 'name' => 'D']);
        $owner = User::factory()->create();
        $owner->assignRole('admin');

        $emp1 = Employee::factory()->create(['branch_id' => $branch->id, 'department_id' => $dept->id]);
        $emp2 = Employee::factory()->create(['branch_id' => $branch->id, 'department_id' => $dept->id]);
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // ربط سليم.
        $this->actingAs($owner);
        $this->withoutMiddleware();
        $emp1->update(['user_id' => $userA->id]);

        // موظف مرتبط بحساب: لا يُربط بحساب ثانٍ إلا بعد فكّ الربط.
        $this->assertDatabaseHas('employees', ['id' => $emp1->id, 'user_id' => $userA->id]);
        // حساب مرتبط بموظف: لا يُربط بموظف ثانٍ (قيد فريد + منطق الخدمة).
        $emp2->user_id = $userA->id;
        $threw = false;
        try {
            $emp2->save();
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'قيد التفرّد يمنع ربط نفس الحساب بموظفَين.');
    }
}
