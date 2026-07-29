<?php

namespace Tests\Feature\Hr;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * الحقول المالية/البنكية تُخفى إلا لمن يملك employees.salary.view (docs/05 §4 — Field-Level).
 */
class EmployeeFieldProtectionTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_salary_hidden_without_permission(): void
    {
        $viewer = $this->userWithPermissions(['employees.view']);
        $employee = Employee::factory()->create(['basic_salary' => 12000, 'bank_account' => 'SA123']);

        $res = $this->actingAsToken($viewer)->getJson("/api/employees/{$employee->id}")->assertOk();

        $this->assertArrayNotHasKey('basic_salary', $res->json('data'));
        $this->assertArrayNotHasKey('bank_account', $res->json('data'));
    }

    public function test_salary_visible_with_permission(): void
    {
        $viewer = $this->userWithPermissions(['employees.view', 'employees.salary.view']);
        $employee = Employee::factory()->create(['basic_salary' => 12000]);

        $res = $this->actingAsToken($viewer)->getJson("/api/employees/{$employee->id}")->assertOk();

        $this->assertEquals(12000, $res->json('data.basic_salary'));
    }
}
