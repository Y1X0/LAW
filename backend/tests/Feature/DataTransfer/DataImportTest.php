<?php

namespace Tests\Feature\DataTransfer;

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
 * استيراد الموظفين من Excel (المرحلة 2): معاينة بلا حفظ + حفظ ذرّي + upsert + تحقّق + صلاحيات.
 */
class DataImportTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function orgUnits(): array
    {
        $branch = Branch::create(['name' => 'الرئيسي', 'code' => 'HQ']);
        $department = Department::create(['branch_id' => $branch->id, 'name' => 'القضايا']);

        return [$branch, $department];
    }

    /** يبني ملف xlsx حقيقيًا (رؤوس + صفوف) ويعيده كـ UploadedFile. */
    private function xlsx(array $headers, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'employees.xlsx', null, null, true);
    }

    private const HEADERS = ['employee_no', 'full_name_ar', 'national_id', 'branch', 'department', 'job_title', 'status', 'basic_salary'];

    public function test_preview_reports_create_and_update_without_saving(): void
    {
        $this->orgUnits();
        Employee::factory()->create(['employee_no' => 'E-1', 'national_id' => '111']);
        $actor = $this->userWithPermissions(['employees.create']);

        $file = $this->xlsx(self::HEADERS, [
            ['E-1', 'موظف قائم', '111', 'الرئيسي', 'القضايا', 'محامٍ', 'active', ''],   // تحديث
            ['E-2', 'موظف جديد', '222', 'الرئيسي', 'القضايا', 'كاتب', 'active', ''],     // إنشاء
        ]);

        $this->actingAsToken($actor)->post('/api/admin/data/import/employees/preview', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('data.create', 1)
            ->assertJsonPath('data.update', 1)
            ->assertJsonPath('data.invalid', 0);

        // معاينة لا تحفظ.
        $this->assertDatabaseMissing('employees', ['employee_no' => 'E-2']);
    }

    public function test_commit_creates_and_updates_employees(): void
    {
        $this->orgUnits();
        Employee::factory()->create(['employee_no' => 'E-1', 'national_id' => '111', 'full_name_ar' => 'اسم قديم']);
        $actor = $this->userWithPermissions(['employees.create', 'employees.salary.view']);

        $file = $this->xlsx(self::HEADERS, [
            ['E-1', 'اسم محدّث', '111', 'الرئيسي', 'القضايا', 'محامٍ', 'active', '5000'],
            ['E-2', 'موظف جديد', '222', 'الرئيسي', 'القضايا', 'كاتب', 'active', '3000'],
        ]);

        $this->actingAsToken($actor)->post('/api/admin/data/import/employees/commit', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 1);

        $this->assertDatabaseHas('employees', ['employee_no' => 'E-1', 'full_name_ar' => 'اسم محدّث', 'basic_salary' => 5000]);
        $this->assertDatabaseHas('employees', ['employee_no' => 'E-2', 'full_name_ar' => 'موظف جديد', 'basic_salary' => 3000]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employees_imported']);
    }

    public function test_commit_is_atomic_when_a_row_is_invalid(): void
    {
        $this->orgUnits();
        $actor = $this->userWithPermissions(['employees.create']);

        $file = $this->xlsx(self::HEADERS, [
            ['E-2', 'صحيح', '222', 'الرئيسي', 'القضايا', 'كاتب', 'active', ''],
            ['E-3', 'فرع خاطئ', '333', 'فرع غير موجود', 'القضايا', 'كاتب', 'active', ''], // خطأ
        ]);

        $this->actingAsToken($actor)->post('/api/admin/data/import/employees/commit', ['file' => $file])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'IMPORT_VALIDATION');

        // الكل-أو-لا-شيء: حتى الصف الصحيح لم يُحفَظ.
        $this->assertDatabaseMissing('employees', ['employee_no' => 'E-2']);
        $this->assertDatabaseMissing('employees', ['employee_no' => 'E-3']);
    }

    public function test_import_requires_create_permission(): void
    {
        $this->orgUnits();
        $viewer = $this->userWithPermissions(['employees.view']); // لا يملك create
        $file = $this->xlsx(self::HEADERS, [['E-9', 'x', '999', 'الرئيسي', 'القضايا', '', 'active', '']]);

        $this->actingAsToken($viewer)->post('/api/admin/data/import/employees/commit', ['file' => $file])
            ->assertStatus(403);
    }

    public function test_salary_is_ignored_on_import_without_salary_permission(): void
    {
        $this->orgUnits();
        $actor = $this->userWithPermissions(['employees.create']); // بلا salary.view
        $file = $this->xlsx(self::HEADERS, [['E-5', 'بلا راتب', '555', 'الرئيسي', 'القضايا', '', 'active', '9999']]);

        $this->actingAsToken($actor)->post('/api/admin/data/import/employees/commit', ['file' => $file])
            ->assertOk()->assertJsonPath('data.created', 1);

        // الراتب المُرسَل تُجوهِل — يبقى الافتراضي (0).
        $this->assertDatabaseMissing('employees', ['employee_no' => 'E-5', 'basic_salary' => 9999]);
        $this->assertDatabaseHas('employees', ['employee_no' => 'E-5', 'basic_salary' => 0]);
    }
}
