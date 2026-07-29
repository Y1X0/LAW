<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class EmployeeValidationTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->userWithPermissions(['employees.create']);
        $this->branch = Branch::create(['name' => 'الرئيسي', 'code' => 'HQ']);
        $this->department = Department::create(['branch_id' => $this->branch->id, 'name' => 'عام']);
    }

    private function postEmployee(array $overrides): TestResponse
    {
        return $this->actingAsToken($this->admin)->postJson('/api/employees', array_merge([
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
            'employee_no' => 'EMP-2001',
            'full_name_ar' => 'سالم',
            'national_id' => '9990001112',
        ], $overrides));
    }

    public function test_required_fields_are_enforced(): void
    {
        $this->actingAsToken($this->admin)->postJson('/api/employees', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['fields' => ['branch_id', 'department_id', 'employee_no', 'full_name_ar', 'national_id']]]);
    }

    public function test_national_id_must_be_unique(): void
    {
        Employee::factory()->create(['national_id' => '9990001112']);

        $this->postEmployee(['national_id' => '9990001112'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_employee_no_must_be_unique(): void
    {
        Employee::factory()->create(['employee_no' => 'EMP-2001']);

        $this->postEmployee(['employee_no' => 'EMP-2001'])->assertStatus(422);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $this->postEmployee(['status' => 'flying'])->assertStatus(422);
    }

    public function test_nonexistent_branch_is_rejected(): void
    {
        $this->postEmployee(['branch_id' => 999999])->assertStatus(422);
    }

    public function test_contract_end_must_not_precede_start(): void
    {
        $this->postEmployee(['contract_start' => '2026-06-01', 'contract_end' => '2026-01-01'])->assertStatus(422);
    }
}
