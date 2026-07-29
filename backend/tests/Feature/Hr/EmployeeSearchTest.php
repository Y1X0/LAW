<?php

namespace Tests\Feature\Hr;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class EmployeeSearchTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_search_by_name_or_number(): void
    {
        $viewer = $this->userWithPermissions(['employees.view']);
        Employee::factory()->create(['full_name_ar' => 'خالد الوكيل', 'employee_no' => 'EMP-A1']);
        Employee::factory()->create(['full_name_ar' => 'عمر الكاتب', 'employee_no' => 'EMP-B2']);

        $res = $this->actingAsToken($viewer)->getJson('/api/employees?search=خالد')->assertOk();
        $this->assertSame(1, $res->json('meta.total'));
        $this->assertSame('خالد الوكيل', $res->json('data.0.full_name_ar'));
    }

    public function test_filter_by_status(): void
    {
        $viewer = $this->userWithPermissions(['employees.view']);
        Employee::factory()->create(['status' => 'active']);
        Employee::factory()->create(['status' => 'suspended']);

        $res = $this->actingAsToken($viewer)->getJson('/api/employees?status=suspended')->assertOk();
        $this->assertSame(1, $res->json('meta.total'));
    }

    public function test_filter_by_department(): void
    {
        $viewer = $this->userWithPermissions(['employees.view']);
        $branch = Branch::create(['name' => 'HQ', 'code' => 'HQ']);
        $depA = Department::create(['branch_id' => $branch->id, 'name' => 'A']);
        $depB = Department::create(['branch_id' => $branch->id, 'name' => 'B']);
        Employee::factory()->create(['branch_id' => $branch->id, 'department_id' => $depA->id]);
        Employee::factory()->create(['branch_id' => $branch->id, 'department_id' => $depB->id]);

        $res = $this->actingAsToken($viewer)->getJson("/api/employees?department_id={$depA->id}")->assertOk();
        $this->assertSame(1, $res->json('meta.total'));
    }

    public function test_pagination_meta_present(): void
    {
        $viewer = $this->userWithPermissions(['employees.view']);
        Employee::factory()->count(3)->create();

        $res = $this->actingAsToken($viewer)->getJson('/api/employees?per_page=2')->assertOk();
        $this->assertSame(2, $res->json('meta.per_page'));
        $this->assertSame(3, $res->json('meta.total'));
        $this->assertSame(2, $res->json('meta.total_pages'));
    }
}
