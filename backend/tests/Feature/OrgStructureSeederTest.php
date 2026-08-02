<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\Core\Seeders\OrgStructureSeeder;
use Modules\DataTransfer\Services\DataImportService;
use Tests\TestCase;

/**
 * الهيكل التنظيمي الأساسي: يثبت أن البذر Idempotent وأنّه يجعل استيراد الموظفين ينجح.
 */
class OrgStructureSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_one_branch_and_all_departments(): void
    {
        $this->seed(OrgStructureSeeder::class);

        $branch = Branch::where('code', OrgStructureSeeder::BRANCH_CODE)->first();
        $this->assertNotNull($branch);
        $this->assertSame(OrgStructureSeeder::BRANCH_NAME, $branch->name);

        $this->assertSame(1, Branch::count());
        $this->assertSame(count(OrgStructureSeeder::DEPARTMENTS), Department::count());

        foreach (OrgStructureSeeder::DEPARTMENTS as $name) {
            $this->assertDatabaseHas('departments', ['branch_id' => $branch->id, 'name' => $name]);
        }
    }

    public function test_running_twice_does_not_duplicate(): void
    {
        $this->seed(OrgStructureSeeder::class);
        $this->seed(OrgStructureSeeder::class);

        $this->assertSame(1, Branch::count());
        $this->assertSame(count(OrgStructureSeeder::DEPARTMENTS), Department::count());
    }

    public function test_employees_import_fails_without_org_then_succeeds_after_seeding(): void
    {
        $service = new DataImportService;
        $row = [[
            'employee_no' => 'IMP-100',
            'full_name_ar' => 'موظف الاستيراد',
            'national_id' => '9001234567',
            'branch' => OrgStructureSeeder::BRANCH_NAME,
            'department' => 'تقنية المعلومات',
            'job_title' => 'مهندس نظم',
            'status' => 'active',
        ]];

        // قبل البذر: الفرع غير موجود ⇒ الصف غير صالح ولا يُحفَظ (ضبط مرجعي).
        $before = $service->commitEmployees($row, false, null);
        $this->assertSame(0, $before['created']);
        $this->assertNotEmpty($before['errors']);
        $this->assertDatabaseMissing('employees', ['employee_no' => 'IMP-100']);

        // بعد بذر الهيكل الأساسي: نفس الصف يُستورَد بنجاح.
        $this->seed(OrgStructureSeeder::class);
        $after = $service->commitEmployees($row, false, null);

        $this->assertSame(1, $after['created']);
        $this->assertSame([], $after['errors']);
        $branch = Branch::where('code', OrgStructureSeeder::BRANCH_CODE)->first();
        $dept = Department::where('branch_id', $branch->id)->where('name', 'تقنية المعلومات')->first();
        $this->assertDatabaseHas('employees', [
            'employee_no' => 'IMP-100',
            'branch_id' => $branch->id,
            'department_id' => $dept->id,
        ]);
    }
}
