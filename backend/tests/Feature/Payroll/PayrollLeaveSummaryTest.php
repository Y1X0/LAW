<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class PayrollLeaveSummaryTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function paidType(): LeaveType
    {
        return LeaveType::create(['name' => 'سنوية', 'code' => 'annual', 'is_paid' => true, 'consumes_balance' => true]);
    }

    private function unpaidType(): LeaveType
    {
        return LeaveType::create(['name' => 'بدون راتب', 'code' => 'unpaid', 'is_paid' => false, 'consumes_balance' => false]);
    }

    private function leave(Employee $e, LeaveType $t, string $from, string $to, string $status = 'approved'): LeaveRequest
    {
        return LeaveRequest::create([
            'employee_id' => $e->id, 'leave_type_id' => $t->id,
            'start_date' => $from, 'end_date' => $to, 'days' => 1, 'status' => $status,
        ]);
    }

    public function test_preview_splits_paid_and_unpaid_leave_days(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $employee = Employee::factory()->create();
        // مدفوعة: 2026-06-08 (اثنين) → 2026-06-11 (خميس) = 4 أيام عمل
        $this->leave($employee, $this->paidType(), '2026-06-08', '2026-06-11');
        // بدون راتب: 2026-06-14 (أحد) → 2026-06-16 (ثلاثاء) = 3 أيام عمل
        $this->leave($employee, $this->unpaidType(), '2026-06-14', '2026-06-16');

        $res = $this->actingAsToken($viewer)->getJson("/api/employees/{$employee->id}/leave-summary?year=2026&month=6")
            ->assertOk();

        $this->assertEquals(4, $res->json('data.paid_leave_days'));
        $this->assertEquals(3, $res->json('data.unpaid_leave_days'));
    }

    public function test_only_approved_leaves_count(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $employee = Employee::factory()->create();
        $type = $this->unpaidType();
        $this->leave($employee, $type, '2026-06-08', '2026-06-09', 'pending');
        $this->leave($employee, $type, '2026-06-10', '2026-06-11', 'rejected');
        $this->leave($employee, $type, '2026-06-15', '2026-06-16', 'cancelled');

        $res = $this->actingAsToken($viewer)->getJson("/api/employees/{$employee->id}/leave-summary?year=2026&month=6")
            ->assertOk();

        $this->assertEquals(0, $res->json('data.unpaid_leave_days'));
    }

    public function test_only_days_within_the_month_are_counted(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $employee = Employee::factory()->create();
        // إجازة تمتد من مايو إلى يونيو — يُحتسب جزء يونيو فقط.
        // 2026-06-01 (اثنين) → 2026-06-03 (أربعاء) داخل يونيو = 3 أيام عمل (والباقي في مايو)
        $this->leave($employee, $this->paidType(), '2026-05-28', '2026-06-03');

        $res = $this->actingAsToken($viewer)->getJson("/api/employees/{$employee->id}/leave-summary?year=2026&month=6")
            ->assertOk();

        $this->assertEquals(3, $res->json('data.paid_leave_days'));
    }

    public function test_non_overlapping_month_returns_zero(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $employee = Employee::factory()->create();
        $this->leave($employee, $this->paidType(), '2026-07-06', '2026-07-08'); // يوليو

        $res = $this->actingAsToken($viewer)->getJson("/api/employees/{$employee->id}/leave-summary?year=2026&month=6")
            ->assertOk();

        $this->assertEquals(0, $res->json('data.paid_leave_days'));
        $this->assertEquals(0, $res->json('data.unpaid_leave_days'));
    }

    public function test_preview_requires_permission(): void
    {
        $noPerm = $this->userWithPermissions([]);
        $employee = Employee::factory()->create();

        $this->actingAsToken($noPerm)->getJson("/api/employees/{$employee->id}/leave-summary?year=2026&month=6")
            ->assertStatus(403);
    }
}
