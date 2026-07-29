<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function orgUnits(): array
    {
        $branch = Branch::create(['name' => 'الرئيسي', 'code' => 'HQ']);
        $department = Department::create(['branch_id' => $branch->id, 'name' => 'القضايا']);

        return [$branch, $department];
    }

    private function payload(Branch $branch, Department $department, array $overrides = []): array
    {
        return array_merge([
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'employee_no' => 'EMP-1001',
            'full_name_ar' => 'أحمد المحامي',
            'national_id' => '1234567890',
        ], $overrides);
    }

    public function test_can_create_employee(): void
    {
        [$branch, $department] = $this->orgUnits();
        $admin = $this->userWithPermissions(['employees.create']);

        $this->actingAsToken($admin)
            ->postJson('/api/employees', $this->payload($branch, $department))
            ->assertCreated()
            ->assertJsonPath('data.full_name_ar', 'أحمد المحامي');

        $this->assertDatabaseHas('employees', ['employee_no' => 'EMP-1001', 'created_by' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employee_created']);
    }

    public function test_can_list_and_show_employees(): void
    {
        $viewer = $this->userWithPermissions(['employees.view']);
        $employee = Employee::factory()->create();

        $this->actingAsToken($viewer)->getJson('/api/employees')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['page', 'per_page', 'total', 'total_pages'], 'errors']);

        $this->actingAsToken($viewer)->getJson("/api/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $employee->id);
    }

    public function test_can_update_employee(): void
    {
        $editor = $this->userWithPermissions(['employees.update']);
        $employee = Employee::factory()->create(['job_title' => 'قديم']);

        $this->actingAsToken($editor)->putJson("/api/employees/{$employee->id}", ['job_title' => 'محامٍ أول'])
            ->assertOk()
            ->assertJsonPath('data.job_title', 'محامٍ أول');

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'job_title' => 'محامٍ أول', 'updated_by' => $editor->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employee_updated']);
    }

    public function test_can_archive_employee_soft_delete(): void
    {
        $manager = $this->userWithPermissions(['employees.delete']);
        $employee = Employee::factory()->create();

        $this->actingAsToken($manager)->deleteJson("/api/employees/{$employee->id}")->assertOk();

        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employee_archived']);
    }

    public function test_create_requires_permission(): void
    {
        [$branch, $department] = $this->orgUnits();
        $user = User::factory()->create(); // بلا صلاحية

        $this->actingAsToken($user)->postJson('/api/employees', $this->payload($branch, $department))
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'FORBIDDEN');
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/employees')
            ->assertStatus(401)
            ->assertJsonPath('errors.code', 'UNAUTHENTICATED');
    }
}
