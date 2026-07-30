<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\EmployeeSalaryProfile;
use Modules\Payroll\Models\PayrollItem;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Models\PayrollRun;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class PayrollApprovalTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** @return array{0:PayrollRun,1:Employee} */
    private function calculatedRun(string $status = 'draft'): array
    {
        $employee = Employee::factory()->create();
        EmployeeSalaryProfile::create(['employee_id' => $employee->id, 'basic_salary' => 3000, 'effective_from' => '2026-01-01', 'is_active' => true]);
        $period = PayrollPeriod::create(['year' => 2026, 'month' => 6, 'status' => $status]);
        $run = PayrollRun::create(['payroll_period_id' => $period->id, 'status' => $status]);
        PayrollItem::create([
            'payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'currency' => 'SAR',
            'basic_salary' => 3000, 'allowances_total' => 500, 'deductions_total' => 100,
            'gross_amount' => 3500, 'net_amount' => 3400, 'breakdown' => ['earnings' => [], 'deductions' => []],
        ]);

        return [$run, $employee];
    }

    public function test_approve_requires_calculated_run(): void
    {
        $approver = $this->userWithPermissions(['payroll.approve']);
        // مسير بلا نتائج (غير محسوب)
        $period = PayrollPeriod::create(['year' => 2026, 'month' => 6, 'status' => 'draft']);
        $run = PayrollRun::create(['payroll_period_id' => $period->id, 'status' => 'draft']);

        $this->actingAsToken($approver)->postJson("/api/payroll-runs/{$run->id}/approve")
            ->assertStatus(422);
    }

    public function test_approve_sets_status_and_approver(): void
    {
        $approver = $this->userWithPermissions(['payroll.approve']);
        [$run] = $this->calculatedRun();

        $this->actingAsToken($approver)->postJson("/api/payroll-runs/{$run->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $fresh = $run->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertNotNull($fresh->approved_by);
        $this->assertNotNull($fresh->approved_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payroll_run_approved']);
    }

    public function test_approve_requires_permission(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        [$run] = $this->calculatedRun();

        $this->actingAsToken($viewer)->postJson("/api/payroll-runs/{$run->id}/approve")
            ->assertStatus(403);
    }

    public function test_cannot_approve_already_approved_run(): void
    {
        $approver = $this->userWithPermissions(['payroll.approve']);
        [$run] = $this->calculatedRun();

        $this->actingAsToken($approver)->postJson("/api/payroll-runs/{$run->id}/approve")->assertOk();
        $this->actingAsToken($approver)->postJson("/api/payroll-runs/{$run->id}/approve")->assertStatus(422);
    }

    public function test_lock_requires_approved_run(): void
    {
        $payer = $this->userWithPermissions(['payroll.pay']);
        [$run] = $this->calculatedRun(); // draft

        $this->actingAsToken($payer)->postJson("/api/payroll-runs/{$run->id}/lock")
            ->assertStatus(422);
    }

    public function test_lock_after_approval_sets_locked(): void
    {
        $approver = $this->userWithPermissions(['payroll.approve', 'payroll.pay']);
        [$run] = $this->calculatedRun();

        $this->actingAsToken($approver)->postJson("/api/payroll-runs/{$run->id}/approve")->assertOk();
        $this->actingAsToken($approver)->postJson("/api/payroll-runs/{$run->id}/lock")
            ->assertOk()->assertJsonPath('data.status', 'locked');

        $fresh = $run->fresh();
        $this->assertSame('locked', $fresh->status);
        $this->assertNotNull($fresh->locked_by);
        $this->assertNotNull($fresh->locked_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payroll_run_locked']);
    }

    public function test_lock_requires_pay_permission(): void
    {
        $approver = $this->userWithPermissions(['payroll.approve']); // بلا payroll.pay
        [$run] = $this->calculatedRun();
        $this->actingAsToken($approver)->postJson("/api/payroll-runs/{$run->id}/approve")->assertOk();

        $this->actingAsToken($approver)->postJson("/api/payroll-runs/{$run->id}/lock")
            ->assertStatus(403);
    }

    public function test_locked_run_cannot_be_recalculated(): void
    {
        $actor = $this->userWithPermissions(['payroll.approve', 'payroll.pay', 'payroll.create']);
        [$run] = $this->calculatedRun();

        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/approve")->assertOk();
        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/lock")->assertOk();

        // بعد القفل: إعادة الحساب مرفوضة (الأرقام نهائية).
        $this->actingAsToken($actor)->postJson("/api/payroll-runs/{$run->id}/calculate")
            ->assertStatus(422);
    }
}
