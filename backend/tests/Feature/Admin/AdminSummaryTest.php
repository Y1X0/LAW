<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * لوحة نظام وحدة التحكّم (ADMIN-4) — نقطة تجميع للقراءة فقط محميّة بـ users.manage.
 */
class AdminSummaryTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_returns_aggregated_platform_stats(): void
    {
        $admin = $this->userWithPermissions(['users.manage']);
        User::factory()->create(['status' => 'suspended']);
        Employee::factory()->count(2)->create(['status' => 'active']);

        $this->actingAsToken($admin)->getJson('/api/admin/summary')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'users' => ['total', 'active', 'suspended', 'locked', 'without_roles'],
                    'employees' => ['total', 'active', 'on_leave', 'suspended', 'terminated'],
                    'rbac' => ['roles', 'permissions'],
                    'legal' => ['cases_total', 'cases_open', 'cases_closed', 'hearings_upcoming'],
                    'hr' => ['leave_pending'],
                    'activity',
                ],
            ]);
    }

    public function test_counts_reflect_data(): void
    {
        $admin = $this->userWithPermissions(['users.manage']);
        User::factory()->create(['status' => 'suspended']);
        Employee::factory()->count(3)->create(['status' => 'active']);

        $res = $this->actingAsToken($admin)->getJson('/api/admin/summary')->assertOk();

        // المدير + المستخدم الموقوف = مستخدمان على الأقل، والموقوف = 1.
        $this->assertGreaterThanOrEqual(2, $res->json('data.users.total'));
        $this->assertSame(1, $res->json('data.users.suspended'));
        $this->assertSame(3, $res->json('data.employees.total'));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/admin/summary')->assertUnauthorized();
    }

    public function test_forbidden_without_users_manage_permission(): void
    {
        $noPerm = $this->userWithPermissions(['employees.view']);

        $this->actingAsToken($noPerm)->getJson('/api/admin/summary')->assertForbidden();
    }
}
