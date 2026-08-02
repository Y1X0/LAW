<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\HR\Models\Employee;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * تحقّق نظام شامل (System Verification) لسلسلة M1: بناء الهيكل التنظيمي من الواجهة
 * (API) ثم استهلاكه فورًا في استيراد الموظفين — بلا أي بذرة/تعديل يدوي. يثبت أن
 * الفروع/الأقسام المُنشأة من الشاشة تُغذّي إنشاء الموظفين مباشرةً، وأن استيراد Excel
 * ما زال يعمل بعد إضافة إدارة الهيكل التنظيمي.
 */
class OrgToEmployeeFlowTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function xlsx(array $headers, array $rows): UploadedFile
    {
        $ss = new Spreadsheet;
        $sheet = $ss->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($ss))->save($path);

        return new UploadedFile($path, 'employees.xlsx', null, null, true);
    }

    public function test_full_chain_ui_org_then_employee_import_with_no_manual_edits(): void
    {
        // مالك بصلاحيات إعداد الشركة كاملةً من الواجهة.
        $owner = $this->userWithPermissions(['org.manage', 'employees.create']);

        // 1) إنشاء عدّة فروع من الواجهة (API).
        $this->actingAsToken($owner)->postJson('/api/branches', ['name' => 'المكتب الرئيسي', 'code' => 'HQ'])->assertStatus(201);
        $this->actingAsToken($owner)->postJson('/api/branches', ['name' => 'فرع جدة', 'code' => 'JED'])->assertStatus(201);

        $hq = Branch::where('code', 'HQ')->first();
        $jed = Branch::where('code', 'JED')->first();

        // 2) إنشاء أقسام داخل كل فرع من الواجهة.
        $this->actingAsToken($owner)->postJson('/api/departments', ['branch_id' => $hq->id, 'name' => 'تقنية المعلومات'])->assertStatus(201);
        $this->actingAsToken($owner)->postJson('/api/departments', ['branch_id' => $jed->id, 'name' => 'التقاضي'])->assertStatus(201);

        // 3) القوائم التي تعتمد على الهيكل تُظهره فورًا (نفس نقاط الاستهلاك للواجهة).
        $this->actingAsToken($owner)->getJson('/api/branches')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAsToken($owner)->getJson("/api/departments?branch_id={$hq->id}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'تقنية المعلومات');

        // 4) استيراد الموظفين يستهلك هذه الأسماء مباشرةً — بلا أي تعديل إضافي ولا بذرة.
        $headers = ['employee_no', 'full_name_ar', 'national_id', 'branch', 'department', 'status'];
        $file = $this->xlsx($headers, [
            ['E-1', 'موظف الرئيسي', '1001', 'المكتب الرئيسي', 'تقنية المعلومات', 'active'],
            ['E-2', 'موظف جدة', '1002', 'فرع جدة', 'التقاضي', 'active'],
        ]);

        $this->actingAsToken($owner)->post('/api/admin/data/import/employees/commit', ['file' => $file])
            ->assertOk()->assertJsonPath('data.created', 2);

        // 5) الموظفون رُبطوا بالفرع/القسم الصحيحين المُنشأين من الواجهة.
        $itDept = Department::where('branch_id', $hq->id)->where('name', 'تقنية المعلومات')->first();
        $this->assertDatabaseHas('employees', ['employee_no' => 'E-1', 'branch_id' => $hq->id, 'department_id' => $itDept->id]);
        $this->assertDatabaseHas('employees', ['employee_no' => 'E-2', 'branch_id' => $jed->id]);
        $this->assertSame(2, Employee::count());
    }
}
