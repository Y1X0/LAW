<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\Core\Seeders\RbacSeeder;
use Modules\HR\Models\Employee;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * دورة حياة الموظف (M1/PR-2): إنشاء/تعديل من الواجهة عبر HR، مع حماية قواعد العمل
 * (القسم يتبع الفرع)، وفصل صلاحيات القراءة/الكتابة التنظيمية (org.view / org.manage).
 */
class EmployeeLifecycleTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function orgUnits(): array
    {
        $hq = Branch::create(['name' => 'المكتب الرئيسي', 'code' => 'HQ']);
        $jed = Branch::create(['name' => 'فرع جدة', 'code' => 'JED']);
        $it = Department::create(['branch_id' => $hq->id, 'name' => 'تقنية المعلومات']);
        $lit = Department::create(['branch_id' => $jed->id, 'name' => 'التقاضي']);

        return compact('hq', 'jed', 'it', 'lit');
    }

    public function test_department_must_belong_to_selected_branch(): void
    {
        ['hq' => $hq, 'lit' => $lit] = $this->orgUnits();
        $actor = $this->userWithPermissions(['employees.create']);

        // قسم «التقاضي» يتبع فرع جدة، لكن الفرع المُرسل هو الرئيسي ⇒ رفض.
        $this->actingAsToken($actor)->postJson('/api/employees', [
            'branch_id' => $hq->id,
            'department_id' => $lit->id,
            'employee_no' => 'E-1',
            'full_name_ar' => 'اسم',
            'national_id' => '111',
        ])->assertStatus(422)->assertJsonPath('errors.fields.department_id.0', 'القسم المختار لا يتبع الفرع المحدّد.');

        $this->assertDatabaseMissing('employees', ['employee_no' => 'E-1']);
    }

    public function test_org_reads_use_org_view_but_writes_require_org_manage(): void
    {
        $this->orgUnits();
        $reader = $this->userWithPermissions(['org.view']);

        $this->actingAsToken($reader)->getJson('/api/branches')->assertOk();
        $this->actingAsToken($reader)->getJson('/api/departments')->assertOk();
        // القراءة مسموحة، الكتابة ممنوعة (تحتاج org.manage).
        $this->actingAsToken($reader)->postJson('/api/branches', ['name' => 'x', 'code' => 'X'])->assertStatus(403);
    }

    public function test_seeded_hr_can_read_org_and_create_an_employee(): void
    {
        $this->seed(RbacSeeder::class);
        ['hq' => $hq, 'it' => $it] = $this->orgUnits();
        $hr = User::factory()->create();
        $hr->assignRole('hr');

        // HR يقرأ الهيكل التنظيمي عبر org.view (لا org.manage).
        $this->actingAsToken($hr)->getJson('/api/branches')->assertOk();
        $this->actingAsToken($hr)->getJson("/api/departments?branch_id={$hq->id}")->assertOk();

        // HR ينشئ موظفاً من الواجهة.
        $this->actingAsToken($hr)->postJson('/api/employees', [
            'branch_id' => $hq->id,
            'department_id' => $it->id,
            'employee_no' => 'EMP-1001',
            'full_name_ar' => 'موظف جديد',
            'national_id' => '2001',
            'status' => 'active',
        ])->assertStatus(201)->assertJsonPath('data.employee_no', 'EMP-1001');

        // يظهر في قائمة HR.
        $this->actingAsToken($hr)->getJson('/api/employees?search=EMP-1001')
            ->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_duplicate_national_id_and_employee_no_are_rejected(): void
    {
        ['hq' => $hq, 'it' => $it] = $this->orgUnits();
        $actor = $this->userWithPermissions(['employees.create']);
        Employee::factory()->create(['employee_no' => 'EMP-9', 'national_id' => '999', 'branch_id' => $hq->id, 'department_id' => $it->id]);

        $this->actingAsToken($actor)->postJson('/api/employees', [
            'branch_id' => $hq->id, 'department_id' => $it->id,
            'employee_no' => 'EMP-9', 'full_name_ar' => 'x', 'national_id' => '1234',
        ])->assertStatus(422);

        $this->actingAsToken($actor)->postJson('/api/employees', [
            'branch_id' => $hq->id, 'department_id' => $it->id,
            'employee_no' => 'EMP-10', 'full_name_ar' => 'x', 'national_id' => '999',
        ])->assertStatus(422);
    }

    /**
     * System Verification (السيناريو الحقيقي الكامل): مالك ينشئ فرعاً وقسماً من الـAPI،
     * HR يراهما وينشئ موظفاً يظهر في قائمته، ثم استيراد Excel ما زال يعمل — بلا بذرة.
     */
    public function test_full_scenario_owner_org_then_hr_creates_employee_then_excel_still_works(): void
    {
        $this->seed(RbacSeeder::class);
        $owner = User::factory()->create();
        $owner->assignRole('admin');
        $hr = User::factory()->create();
        $hr->assignRole('hr');

        // 1-3) المالك ينشئ فرعاً وقسماً من الواجهة.
        $this->actingAsToken($owner)->postJson('/api/branches', ['name' => 'المكتب الرئيسي', 'code' => 'HQ'])->assertStatus(201);
        $hq = Branch::where('code', 'HQ')->first();
        $this->actingAsToken($owner)->postJson('/api/departments', ['branch_id' => $hq->id, 'name' => 'تقنية المعلومات'])->assertStatus(201);
        $it = Department::where('name', 'تقنية المعلومات')->first();

        // 4-5) HR يدخل ويرى الفرع والقسم.
        $this->actingAsToken($hr)->getJson('/api/branches')->assertOk()->assertJsonPath('data.0.code', 'HQ');
        $this->actingAsToken($hr)->getJson("/api/departments?branch_id={$hq->id}")->assertOk()->assertJsonCount(1, 'data');

        // 6-7) HR ينشئ موظفاً يظهر في قائمته.
        $this->actingAsToken($hr)->postJson('/api/employees', [
            'branch_id' => $hq->id, 'department_id' => $it->id,
            'employee_no' => 'EMP-1', 'full_name_ar' => 'موظف يدوي', 'national_id' => '5001', 'status' => 'active',
        ])->assertStatus(201);
        $this->actingAsToken($hr)->getJson('/api/employees')->assertOk()->assertJsonPath('meta.total', 1);

        // 9) استيراد Excel ما زال يعمل جنباً إلى جنب مع الإنشاء اليدوي.
        $ss = new Spreadsheet;
        $ss->getActiveSheet()->fromArray(['employee_no', 'full_name_ar', 'national_id', 'branch', 'department', 'status'], null, 'A1');
        $ss->getActiveSheet()->fromArray(['EMP-2', 'موظف مستورد', '5002', 'المكتب الرئيسي', 'تقنية المعلومات', 'active'], null, 'A2');
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($ss))->save($path);
        $file = new UploadedFile($path, 'employees.xlsx', null, null, true);

        $this->actingAsToken($owner)->post('/api/admin/data/import/employees/commit', ['file' => $file])
            ->assertOk()->assertJsonPath('data.created', 1);

        // 8) قابلية الربط لاحقاً محفوظة: علاقة 1:1 (user_id فريد) — الحساب غير مربوط بعد.
        $this->assertSame(2, Employee::count());
        $this->assertNull(Employee::where('employee_no', 'EMP-1')->first()->user_id);
    }
}
