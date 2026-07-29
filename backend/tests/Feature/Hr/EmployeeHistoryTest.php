<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class EmployeeHistoryTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_history_returns_audit_events_for_employee(): void
    {
        $hr = $this->userWithPermissions(['employees.view', 'employees.update']);
        $employee = Employee::factory()->create();

        // توليد أحداث تدقيق (تعديل الموظف)
        $this->actingAsToken($hr)->putJson("/api/employees/{$employee->id}", ['job_title' => 'محدّث'])->assertOk();

        $res = $this->actingAsToken($hr)->getJson("/api/employees/{$employee->id}/history")->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['page', 'per_page', 'total', 'total_pages'], 'errors']);

        $actions = collect($res->json('data'))->pluck('action');
        $this->assertTrue($actions->contains('employee_updated'));
    }

    public function test_history_requires_permission(): void
    {
        $viewer = User::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAsToken($viewer)->getJson("/api/employees/{$employee->id}/history")->assertStatus(403);
    }
}
