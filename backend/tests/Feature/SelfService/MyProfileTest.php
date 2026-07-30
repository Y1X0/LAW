<?php

namespace Tests\Feature\SelfService;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class MyProfileTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** @return array{0:User, 1:Employee} */
    private function linkedUser(array $permissions = ['profile.update_own']): array
    {
        $user = $this->userWithPermissions($permissions);
        $employee = Employee::factory()->create([
            'user_id' => $user->id, 'job_title' => 'محامٍ', 'basic_salary' => 8000, 'phone' => '0500000000',
        ]);

        return [$user, $employee];
    }

    public function test_show_returns_my_profile(): void
    {
        [$user, $employee] = $this->linkedUser();

        $res = $this->actingAsToken($user)->getJson('/api/me/profile')->assertOk();

        $this->assertSame($employee->full_name_ar, $res->json('data.name'));
        $this->assertSame('محامٍ', $res->json('data.job_title'));
        $this->assertSame('0500000000', $res->json('data.phone'));
    }

    public function test_can_update_allowed_fields(): void
    {
        [$user, $employee] = $this->linkedUser();

        $this->actingAsToken($user)->patchJson('/api/me/profile', [
            'phone' => '0555555555',
            'address' => 'الرياض - حي النخيل',
            'emergency_contact_name' => 'أبو أحمد',
            'emergency_contact_phone' => '0566666666',
        ])->assertOk()->assertJsonPath('data.phone', '0555555555');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'phone' => '0555555555',
            'address' => 'الرياض - حي النخيل',
            'emergency_contact_name' => 'أبو أحمد',
            'emergency_contact_phone' => '0566666666',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'employee_profile_self_updated', 'auditable_id' => $employee->id,
        ]);
    }

    public function test_cannot_update_sensitive_fields(): void
    {
        [$user, $employee] = $this->linkedUser();
        $otherBranch = Branch::create(['name' => 'جدة', 'code' => 'JEDX']);
        $otherDept = Department::create(['branch_id' => $otherBranch->id, 'name' => 'أخرى']);
        $manager = Employee::factory()->create();

        $this->actingAsToken($user)->patchJson('/api/me/profile', [
            'phone' => '0555555555', // مسموح
            // محاولة تعديل حقول حسّاسة — يجب أن تُتجاهَل بالكامل
            'basic_salary' => 99999,
            'job_title' => 'المدير التنفيذي',
            'department_id' => $otherDept->id,
            'branch_id' => $otherBranch->id,
            'manager_id' => $manager->id,
        ])->assertOk();

        $fresh = $employee->fresh();
        $this->assertSame('0555555555', $fresh->phone);           // تغيّر المسموح
        $this->assertEquals(8000, (float) $fresh->basic_salary);  // الراتب ثابت
        $this->assertSame('محامٍ', $fresh->job_title);            // الوظيفة ثابتة
        $this->assertNotSame($otherDept->id, $fresh->department_id);
        $this->assertNotSame($otherBranch->id, $fresh->branch_id);
        $this->assertNull($fresh->manager_id);
    }

    public function test_update_affects_only_my_record(): void
    {
        [$user, $employee] = $this->linkedUser();
        $other = Employee::factory()->create(['phone' => '0511111111']);

        $this->actingAsToken($user)->patchJson('/api/me/profile', ['phone' => '0500000001'])->assertOk();

        $this->assertSame('0511111111', $other->fresh()->phone); // موظف آخر لم يتأثّر
    }

    public function test_requires_permission(): void
    {
        [$user] = $this->linkedUser([]); // موظف مرتبط بلا صلاحية

        $this->actingAsToken($user)->getJson('/api/me/profile')->assertStatus(403);
        $this->actingAsToken($user)->patchJson('/api/me/profile', ['phone' => '0500000002'])->assertStatus(403);
    }

    public function test_forbidden_for_unlinked_user(): void
    {
        $user = $this->userWithPermissions(['profile.update_own']); // بلا موظف مرتبط

        $this->actingAsToken($user)->getJson('/api/me/profile')
            ->assertStatus(403)->assertJsonPath('errors.code', 'NO_LINKED_EMPLOYEE');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/me/profile')->assertStatus(401);
    }
}
