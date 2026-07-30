<?php

namespace Tests\Feature\Leave;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Leave\Models\LeaveType;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class LeaveTypeBalanceTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_can_create_and_list_leave_types(): void
    {
        $admin = $this->userWithPermissions(['leaves.approve', 'leaves.request']);

        $this->actingAsToken($admin)->postJson('/api/leave-types', [
            'name' => 'مرضية', 'code' => 'sick', 'requires_attachment' => true, 'default_annual_days' => 14,
        ])->assertCreated()->assertJsonPath('data.code', 'sick');

        $this->assertDatabaseHas('leave_types', ['code' => 'sick', 'requires_attachment' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'leave_type_created']);
        $this->actingAsToken($admin)->getJson('/api/leave-types')->assertOk();
    }

    public function test_type_code_is_unique(): void
    {
        $admin = $this->userWithPermissions(['leaves.approve']);
        LeaveType::create(['name' => 'سنوية', 'code' => 'annual']);

        $this->actingAsToken($admin)->postJson('/api/leave-types', [
            'name' => 'أخرى', 'code' => 'annual',
        ])->assertStatus(422);
    }

    public function test_managing_types_requires_approve_permission(): void
    {
        $requester = $this->userWithPermissions(['leaves.request']);

        $this->actingAsToken($requester)->postJson('/api/leave-types', [
            'name' => 'x', 'code' => 'x',
        ])->assertStatus(403);
    }

    public function test_set_and_view_employee_balance(): void
    {
        $hr = $this->userWithPermissions(['leaves.approve', 'leaves.view_all']);
        $employee = Employee::factory()->create();
        $type = LeaveType::create(['name' => 'سنوية', 'code' => 'annual', 'default_annual_days' => 20]);

        $this->actingAsToken($hr)->postJson("/api/employees/{$employee->id}/leave-balances", [
            'leave_type_id' => $type->id, 'year' => 2026, 'entitled_days' => 21,
        ])->assertCreated()->assertJsonPath('data.entitled_days', 21);

        $this->assertDatabaseHas('audit_logs', ['action' => 'leave_balance_set']);

        $res = $this->actingAsToken($hr)->getJson("/api/employees/{$employee->id}/leave-balances?year=2026")
            ->assertOk();
        $this->assertSame(21.0, (float) $res->json('data.0.remaining_days'));

        // upsert لا يُنشئ سجلاً ثانياً
        $this->actingAsToken($hr)->postJson("/api/employees/{$employee->id}/leave-balances", [
            'leave_type_id' => $type->id, 'year' => 2026, 'entitled_days' => 25,
        ])->assertOk();
        $this->assertDatabaseCount('leave_balances', 1);
    }

    public function test_leave_routes_require_authentication(): void
    {
        $this->getJson('/api/leave-types')->assertStatus(401);
        $this->getJson('/api/leave-requests')->assertStatus(401);
    }
}
