<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * إدارة الهيكل التنظيمي (M1): CRUD للفروع والأقسام من الواجهة بصلاحية org.manage،
 * مع حماية النزاهة (منع حذف المرتبط) وتفرّد الأكواد/أسماء الأقسام داخل الفرع.
 */
class OrgManagementTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_requires_org_manage_permission(): void
    {
        $viewer = $this->userWithPermissions(['employees.view']); // لا يملك org.manage
        $this->actingAsToken($viewer)->getJson('/api/branches')->assertStatus(403);
        $this->actingAsToken($viewer)->postJson('/api/branches', ['name' => 'x', 'code' => 'X'])->assertStatus(403);
    }

    public function test_admin_can_create_list_update_and_delete_a_branch(): void
    {
        $actor = $this->userWithPermissions(['org.view', 'org.manage']);

        $this->actingAsToken($actor)->postJson('/api/branches', [
            'name' => 'فرع جدة', 'code' => 'JED', 'city' => 'جدة',
        ])->assertStatus(201)->assertJsonPath('data.code', 'JED');

        $branch = Branch::where('code', 'JED')->first();
        $this->assertNotNull($branch);

        $this->actingAsToken($actor)->getJson('/api/branches')
            ->assertOk()->assertJsonPath('data.0.code', 'JED');

        $this->actingAsToken($actor)->putJson("/api/branches/{$branch->id}", ['name' => 'فرع جدة الرئيسي'])
            ->assertOk()->assertJsonPath('data.name', 'فرع جدة الرئيسي');

        $this->actingAsToken($actor)->deleteJson("/api/branches/{$branch->id}")->assertOk();
        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }

    public function test_branch_code_must_be_unique(): void
    {
        $actor = $this->userWithPermissions(['org.view', 'org.manage']);
        Branch::create(['name' => 'أ', 'code' => 'HQ']);

        $this->actingAsToken($actor)->postJson('/api/branches', ['name' => 'ب', 'code' => 'HQ'])
            ->assertStatus(422);
    }

    public function test_branch_with_employees_cannot_be_deleted(): void
    {
        $actor = $this->userWithPermissions(['org.view', 'org.manage']);
        $branch = Branch::create(['name' => 'الرئيسي', 'code' => 'HQ']);
        Employee::factory()->create(['branch_id' => $branch->id]);

        $this->actingAsToken($actor)->deleteJson("/api/branches/{$branch->id}")
            ->assertStatus(422)->assertJsonPath('errors.code', 'BRANCH_IN_USE');
        $this->assertDatabaseHas('branches', ['id' => $branch->id]);
    }

    public function test_department_is_created_under_a_branch_and_name_is_unique_within_it(): void
    {
        $actor = $this->userWithPermissions(['org.view', 'org.manage']);
        $branch = Branch::create(['name' => 'الرئيسي', 'code' => 'HQ']);

        $this->actingAsToken($actor)->postJson('/api/departments', ['branch_id' => $branch->id, 'name' => 'التقاضي'])
            ->assertStatus(201)->assertJsonPath('data.name', 'التقاضي');

        // نفس الاسم في نفس الفرع مرفوض.
        $this->actingAsToken($actor)->postJson('/api/departments', ['branch_id' => $branch->id, 'name' => 'التقاضي'])
            ->assertStatus(422);

        // نفس الاسم في فرع آخر مقبول.
        $other = Branch::create(['name' => 'فرع', 'code' => 'BR2']);
        $this->actingAsToken($actor)->postJson('/api/departments', ['branch_id' => $other->id, 'name' => 'التقاضي'])
            ->assertStatus(201);
    }

    public function test_departments_can_be_filtered_by_branch(): void
    {
        $actor = $this->userWithPermissions(['org.view', 'org.manage']);
        $a = Branch::create(['name' => 'أ', 'code' => 'A']);
        $b = Branch::create(['name' => 'ب', 'code' => 'B']);
        Department::create(['branch_id' => $a->id, 'name' => 'قسم أ']);
        Department::create(['branch_id' => $b->id, 'name' => 'قسم ب']);

        $this->actingAsToken($actor)->getJson("/api/departments?branch_id={$a->id}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'قسم أ');
    }

    public function test_department_with_employees_cannot_be_deleted(): void
    {
        $actor = $this->userWithPermissions(['org.view', 'org.manage']);
        $branch = Branch::create(['name' => 'الرئيسي', 'code' => 'HQ']);
        $dept = Department::create(['branch_id' => $branch->id, 'name' => 'المالية']);
        Employee::factory()->create(['branch_id' => $branch->id, 'department_id' => $dept->id]);

        $this->actingAsToken($actor)->deleteJson("/api/departments/{$dept->id}")
            ->assertStatus(422)->assertJsonPath('errors.code', 'DEPARTMENT_IN_USE');
    }
}
