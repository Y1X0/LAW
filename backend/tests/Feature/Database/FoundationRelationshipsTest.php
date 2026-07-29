<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\ActivityLog;
use Modules\Core\Models\AuditLog;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Tests\TestCase;

/**
 * يتحقق من العلاقات الأساسية بين كيانات النواة وسلامة القيود (Issue #10).
 */
class FoundationRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_has_departments_with_hierarchy(): void
    {
        $branch = Branch::create(['name' => 'المكتب الرئيسي', 'code' => 'HQ']);
        $parent = Department::create(['branch_id' => $branch->id, 'name' => 'القضايا']);
        $child = Department::create(['branch_id' => $branch->id, 'name' => 'القضايا المدنية', 'parent_id' => $parent->id]);

        $this->assertCount(2, $branch->departments);
        $this->assertTrue($parent->children->contains($child));
        $this->assertEquals($parent->id, $child->parent->id);
        $this->assertEquals($branch->id, $child->branch->id);
    }

    public function test_role_permission_many_to_many(): void
    {
        $role = Role::create(['name' => 'lawyer', 'display_name' => 'محامٍ']);
        $p1 = Permission::create(['name' => 'cases.view', 'module' => 'cases']);
        $p2 = Permission::create(['name' => 'cases.create', 'module' => 'cases']);

        $role->permissions()->attach([$p1->id, $p2->id]);

        $this->assertCount(2, $role->fresh()->permissions);
        $this->assertTrue($p1->fresh()->roles->contains($role));
    }

    public function test_user_roles_with_branch_scoped_pivot(): void
    {
        $branch = Branch::create(['name' => 'فرع جدة', 'code' => 'JED']);
        $user = User::factory()->create();
        $role = Role::create(['name' => 'hr', 'display_name' => 'موارد بشرية']);

        $user->roles()->attach($role->id, ['branch_id' => $branch->id]);

        $this->assertCount(1, $user->fresh()->roles);
        $this->assertEquals($branch->id, $user->fresh()->roles->first()->pivot->branch_id);
    }

    public function test_setting_value_is_cast_to_array(): void
    {
        $setting = Setting::create([
            'group' => 'general',
            'key' => 'office',
            'value' => ['name' => 'LawFirm', 'timezone' => 'Asia/Riyadh'],
        ]);

        $this->assertIsArray($setting->fresh()->value);
        $this->assertSame('LawFirm', $setting->fresh()->value['name']);
    }

    public function test_audit_log_casts_json_and_has_no_updated_at(): void
    {
        $log = AuditLog::create([
            'action' => 'update',
            'auditable_type' => 'Case',
            'auditable_id' => 1,
            'old_values' => ['status' => 'open'],
            'new_values' => ['status' => 'closed'],
        ]);

        $this->assertIsArray($log->fresh()->new_values);
        $this->assertNull(AuditLog::UPDATED_AT);
        $this->assertNotNull($log->created_at);
    }

    public function test_activity_log_records_user_action(): void
    {
        $user = User::factory()->create();
        $activity = ActivityLog::create([
            'user_id' => $user->id,
            'description' => 'أنشأ قضية جديدة',
            'subject_type' => 'Case',
            'subject_id' => 5,
        ]);

        $this->assertEquals($user->id, $activity->user->id);
    }
}
