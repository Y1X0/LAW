<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Models\PayrollRun;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class PayrollRunTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function period(): PayrollPeriod
    {
        return PayrollPeriod::create(['year' => 2026, 'month' => 6, 'status' => 'draft']);
    }

    public function test_can_create_run_for_period(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);
        $period = $this->period();

        $res = $this->actingAsToken($actor)->postJson("/api/payroll-periods/{$period->id}/runs", [
            'notes' => 'مسير تجريبي',
        ])->assertCreated()->assertJsonPath('data.status', 'draft');

        $this->assertSame($period->id, PayrollRun::find($res->json('data.id'))->payroll_period_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payroll_run_created']);
    }

    public function test_run_belongs_to_period(): void
    {
        $period = $this->period();
        $run = PayrollRun::create(['payroll_period_id' => $period->id, 'status' => 'draft']);

        $this->assertSame($period->id, $run->period->id);
    }

    public function test_can_list_runs_of_period(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $period = $this->period();
        PayrollRun::create(['payroll_period_id' => $period->id, 'status' => 'draft']);

        $this->actingAsToken($viewer)->getJson("/api/payroll-periods/{$period->id}/runs")
            ->assertOk()->assertJsonPath('data.0.payroll_period_id', $period->id);
    }

    public function test_create_run_requires_permission(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        $period = $this->period();

        $this->actingAsToken($viewer)->postJson("/api/payroll-periods/{$period->id}/runs")
            ->assertStatus(403);
    }
}
