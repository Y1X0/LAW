<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeContract;
use Modules\HR\Models\EmployeeDocument;
use Modules\HR\Models\Position;
use Tests\TestCase;

class EmployeeRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_has_position_contracts_and_documents(): void
    {
        $position = Position::create(['title' => 'محامٍ']);
        $employee = Employee::factory()->create(['position_id' => $position->id]);

        EmployeeContract::create([
            'employee_id' => $employee->id, 'contract_type' => 'permanent', 'start_date' => '2026-01-01',
        ]);
        EmployeeDocument::create([
            'employee_id' => $employee->id, 'doc_type' => 'other', 'title' => 'ملف', 'file_path' => 'x.pdf',
        ]);

        $this->assertSame($position->id, $employee->position->id);
        $this->assertCount(1, $employee->contracts);
        $this->assertCount(1, $employee->documents);
    }

    public function test_deleting_employee_cascades_contracts(): void
    {
        $employee = Employee::factory()->create();
        EmployeeContract::create([
            'employee_id' => $employee->id, 'contract_type' => 'permanent', 'start_date' => '2026-01-01',
        ]);

        $employee->forceDelete(); // حذف فعلي لاختبار cascade على مستوى القاعدة

        $this->assertDatabaseCount('employee_contracts', 0);
    }
}
