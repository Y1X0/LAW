<?php

namespace Tests\Feature\Hr;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeDocument;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class EmployeeDocumentTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_can_add_and_list_documents(): void
    {
        $hr = $this->userWithPermissions(['employees.update', 'employees.view']);
        $employee = Employee::factory()->create();

        $this->actingAsToken($hr)->postJson("/api/employees/{$employee->id}/documents", [
            'doc_type' => 'id_copy',
            'title' => 'صورة الهوية',
            'file_path' => 'employees/1/id.pdf',
            'expiry_date' => '2030-01-01',
        ])->assertCreated();

        $this->assertDatabaseHas('employee_documents', ['employee_id' => $employee->id, 'doc_type' => 'id_copy']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employee_document_added']);

        $this->actingAsToken($hr)->getJson("/api/employees/{$employee->id}/documents")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_expiry_date_is_stored_and_returned(): void
    {
        $hr = $this->userWithPermissions(['employees.update', 'employees.view']);
        $employee = Employee::factory()->create();

        $res = $this->actingAsToken($hr)->postJson("/api/employees/{$employee->id}/documents", [
            'doc_type' => 'certificate', 'title' => 'شهادة', 'file_path' => 'c.pdf',
            'expiry_date' => '2030-06-30',
        ])->assertCreated();

        // تأكيد قابل للنقل بين SQLite و PostgreSQL عبر النموذج (لا سلسلة قاعدة البيانات الخام)
        $this->assertStringStartsWith('2030-06-30', (string) $res->json('data.expiry_date'));
        $this->assertSame('2030-06-30', $employee->documents()->first()->expiry_date->toDateString());
    }

    public function test_document_of_other_employee_returns_404(): void
    {
        $hr = $this->userWithPermissions(['employees.update']);
        $employeeA = Employee::factory()->create();
        $employeeB = Employee::factory()->create();
        $doc = EmployeeDocument::create([
            'employee_id' => $employeeA->id, 'doc_type' => 'other', 'title' => 'x', 'file_path' => 'x.pdf',
        ]);

        // محاولة حذف مستند الموظف A عبر مسار الموظف B → 404
        $this->actingAsToken($hr)->deleteJson("/api/employees/{$employeeB->id}/documents/{$doc->id}")
            ->assertStatus(404);
    }

    public function test_document_type_is_validated(): void
    {
        $hr = $this->userWithPermissions(['employees.update']);
        $employee = Employee::factory()->create();

        $this->actingAsToken($hr)->postJson("/api/employees/{$employee->id}/documents", [
            'doc_type' => 'invalid', 'title' => 'x', 'file_path' => 'x.pdf',
        ])->assertStatus(422);
    }

    public function test_can_archive_document_soft_delete(): void
    {
        $hr = $this->userWithPermissions(['employees.update']);
        $employee = Employee::factory()->create();
        $doc = EmployeeDocument::create([
            'employee_id' => $employee->id, 'doc_type' => 'other',
            'title' => 'ملف', 'file_path' => 'x.pdf',
        ]);

        $this->actingAsToken($hr)->deleteJson("/api/employees/{$employee->id}/documents/{$doc->id}")->assertOk();

        $this->assertSoftDeleted('employee_documents', ['id' => $doc->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employee_document_removed']);
    }

    public function test_document_write_requires_permission(): void
    {
        $viewer = $this->userWithPermissions(['employees.view']);
        $employee = Employee::factory()->create();

        $this->actingAsToken($viewer)->postJson("/api/employees/{$employee->id}/documents", [
            'doc_type' => 'other', 'title' => 'x', 'file_path' => 'x.pdf',
        ])->assertStatus(403);
    }
}
