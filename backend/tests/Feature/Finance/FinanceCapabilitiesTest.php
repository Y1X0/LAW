<?php

namespace Tests\Feature\Finance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * نقطة قدرات المستخدم المالية (Phase 6 · PR-5) — مصدر الواجهة لإظهار الأزرار.
 */
class FinanceCapabilitiesTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_reports_true_flags_for_held_permissions(): void
    {
        $user = $this->userWithPermissions(['invoices.view', 'invoices.create', 'invoices.approve']);

        $this->actingAsToken($user)
            ->getJson('/api/finance/capabilities')
            ->assertOk()
            ->assertJsonPath('data.can_view', true)
            ->assertJsonPath('data.can_create', true)
            ->assertJsonPath('data.can_approve', true);
    }

    public function test_reports_false_for_missing_permissions(): void
    {
        $user = $this->userWithPermissions(['invoices.view']);

        $this->actingAsToken($user)
            ->getJson('/api/finance/capabilities')
            ->assertOk()
            ->assertJsonPath('data.can_view', true)
            ->assertJsonPath('data.can_create', false)
            ->assertJsonPath('data.can_approve', false);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/finance/capabilities')->assertStatus(401);
    }
}
