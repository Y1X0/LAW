<?php

namespace Tests\Feature\DataTransfer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * تصدير Excel (المرحلة 1) — قراءة فقط، محميّ بصلاحيات العرض، مع إخفاء الراتب.
 */
class DataExportTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** يقرأ رؤوس أول صف من محتوى xlsx مُنزّل. */
    private function headersOf(string $content): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        file_put_contents($tmp, $content);
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        $row = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1', null, true, false)[0];
        @unlink($tmp);

        return array_values(array_filter($row, fn ($v) => $v !== null && $v !== ''));
    }

    private function contentColumnValues(string $content, int $col = 1): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        file_put_contents($tmp, $content);
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        $all = $sheet->toArray(null, true, false);
        @unlink($tmp);
        array_shift($all); // إسقاط صف الرؤوس

        return array_map(fn ($r) => $r[$col - 1] ?? null, $all);
    }

    public function test_export_employees_returns_valid_xlsx_with_salary_when_permitted(): void
    {
        $actor = $this->userWithPermissions(['employees.view', 'employees.salary.view']);
        Employee::factory()->create(['employee_no' => 'EMP-777', 'basic_salary' => 12000]);

        $res = $this->actingAsToken($actor)->get('/api/admin/data/export/employees');
        $res->assertOk();
        $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $content = $res->streamedContent();
        $headers = $this->headersOf($content);
        $this->assertContains('employee_no', $headers);
        $this->assertContains('basic_salary', $headers); // ظاهر مع الصلاحية
        $this->assertContains('EMP-777', $this->contentColumnValues($content, 1));
    }

    public function test_export_employees_masks_salary_without_permission(): void
    {
        $actor = $this->userWithPermissions(['employees.view']); // بلا salary.view
        Employee::factory()->create(['employee_no' => 'EMP-888', 'basic_salary' => 12000]);

        $content = $this->actingAsToken($actor)->get('/api/admin/data/export/employees')
            ->assertOk()->streamedContent();

        $headers = $this->headersOf($content);
        $this->assertContains('employee_no', $headers);
        $this->assertNotContains('basic_salary', $headers); // مخفي بلا الصلاحية
        $this->assertNotContains('bank_account', $headers);
    }

    public function test_export_requires_view_permission(): void
    {
        $actor = $this->userWithPermissions(['dashboard.view_own']); // لا يملك employees.view
        $this->actingAsToken($actor)->get('/api/admin/data/export/employees')->assertStatus(403);
    }

    public function test_export_requires_authentication(): void
    {
        $this->get('/api/admin/data/export/employees')->assertStatus(401);
    }

    public function test_all_entity_exports_return_xlsx(): void
    {
        $actor = $this->userWithPermissions([
            'employees.view', 'attendance.view', 'leaves.view_all', 'payroll.view', 'clients.view', 'cases.view_all',
        ]);

        foreach (['employees', 'attendance', 'leave-requests', 'payroll-items', 'clients', 'cases'] as $entity) {
            $res = $this->actingAsToken($actor)->get("/api/admin/data/export/{$entity}");
            $res->assertOk();
            $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            // كل تصدير يحوي صف رؤوس واحدًا على الأقل.
            $this->assertNotEmpty($this->headersOf($res->streamedContent()), "export {$entity} lacks headers");
        }
    }
}
