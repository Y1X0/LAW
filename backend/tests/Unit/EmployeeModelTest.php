<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\HR\Models\Employee;
use Tests\TestCase;

class EmployeeModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_belongs_to_branch_and_department(): void
    {
        $employee = Employee::factory()->create();

        $this->assertInstanceOf(Branch::class, $employee->branch);
        $this->assertInstanceOf(Department::class, $employee->department);
    }

    public function test_manager_and_subordinates_relationship(): void
    {
        $manager = Employee::factory()->create();
        $report = Employee::factory()->create(['manager_id' => $manager->id]);

        $this->assertSame($manager->id, $report->manager->id);
        $this->assertTrue($manager->subordinates->contains($report));
    }
}
