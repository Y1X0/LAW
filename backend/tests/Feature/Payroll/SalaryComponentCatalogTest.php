<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Payroll\Models\SalaryComponent;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class SalaryComponentCatalogTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_can_create_and_list_components(): void
    {
        $admin = $this->userWithPermissions(['payroll.create', 'payroll.view']);

        $this->actingAsToken($admin)->postJson('/api/salary-components', [
            'name' => 'بدل سكن', 'code' => 'housing', 'type' => 'allowance', 'value_type' => 'fixed',
        ])->assertCreated()->assertJsonPath('data.type', 'allowance');

        $this->assertDatabaseHas('salary_components', ['code' => 'housing', 'type' => 'allowance']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'salary_component_created']);
        $this->actingAsToken($admin)->getJson('/api/salary-components')->assertOk();
    }

    public function test_component_type_is_validated(): void
    {
        $admin = $this->userWithPermissions(['payroll.create']);

        $this->actingAsToken($admin)->postJson('/api/salary-components', [
            'name' => 'خاطئ', 'code' => 'x', 'type' => 'bonus', // نوع غير مسموح
        ])->assertStatus(422)->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_component_code_is_unique(): void
    {
        $admin = $this->userWithPermissions(['payroll.create']);
        SalaryComponent::create(['name' => 'مواصلات', 'code' => 'transport', 'type' => 'allowance']);

        $this->actingAsToken($admin)->postJson('/api/salary-components', [
            'name' => 'أخرى', 'code' => 'transport', 'type' => 'allowance',
        ])->assertStatus(422);
    }

    public function test_managing_catalog_requires_permission(): void
    {
        $viewer = $this->userWithPermissions(['payroll.view']);

        $this->actingAsToken($viewer)->postJson('/api/salary-components', [
            'name' => 'x', 'code' => 'x', 'type' => 'allowance',
        ])->assertStatus(403);
    }

    public function test_can_deactivate_component_in_catalog(): void
    {
        $admin = $this->userWithPermissions(['payroll.create']);
        $component = SalaryComponent::create(['name' => 'هاتف', 'code' => 'phone', 'type' => 'allowance']);

        $this->actingAsToken($admin)->putJson("/api/salary-components/{$component->id}", ['is_active' => false])
            ->assertOk();

        $this->assertFalse($component->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'salary_component_updated']);
    }

    public function test_routes_require_authentication(): void
    {
        $this->getJson('/api/salary-components')->assertStatus(401);
    }
}
