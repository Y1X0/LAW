<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Payroll\Models\PayrollPeriod;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class PayrollPeriodTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_can_create_payroll_period(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);

        $this->actingAsToken($actor)->postJson('/api/payroll-periods', ['year' => 2026, 'month' => 6])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.month', 6);

        $this->assertDatabaseHas('payroll_periods', ['year' => 2026, 'month' => 6, 'status' => 'draft']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payroll_period_created']);
    }

    public function test_duplicate_period_is_rejected(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);

        $this->actingAsToken($actor)->postJson('/api/payroll-periods', ['year' => 2026, 'month' => 6])->assertCreated();
        $this->actingAsToken($actor)->postJson('/api/payroll-periods', ['year' => 2026, 'month' => 6])
            ->assertStatus(422)->assertJsonPath('errors.code', 'VALIDATION_ERROR');

        $this->assertDatabaseCount('payroll_periods', 1);
    }

    public function test_month_is_validated(): void
    {
        $actor = $this->userWithPermissions(['payroll.create']);

        $this->actingAsToken($actor)->postJson('/api/payroll-periods', ['year' => 2026, 'month' => 13])
            ->assertStatus(422);
    }

    public function test_create_requires_permission(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);

        $this->actingAsToken($viewer)->postJson('/api/payroll-periods', ['year' => 2026, 'month' => 6])
            ->assertStatus(403);
    }

    public function test_can_list_and_filter_periods(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);
        PayrollPeriod::create(['year' => 2026, 'month' => 5, 'status' => 'draft']);
        PayrollPeriod::create(['year' => 2026, 'month' => 6, 'status' => 'approved']);

        $this->actingAsToken($viewer)->getJson('/api/payroll-periods')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['page', 'per_page', 'total', 'total_pages'], 'errors']);

        $res = $this->actingAsToken($viewer)->getJson('/api/payroll-periods?status=approved')->assertOk();
        $this->assertSame(1, $res->json('meta.total'));
    }

    public function test_routes_require_authentication(): void
    {
        $this->getJson('/api/payroll-periods')->assertStatus(401);
    }
}
