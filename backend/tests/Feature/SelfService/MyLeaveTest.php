<?php

namespace Tests\Feature\SelfService;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveType;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class MyLeaveTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private const PERMS = ['leave.view_own', 'leave.request_own'];

    /** @return array{0:User, 1:Employee} */
    private function linkedUser(array $permissions = self::PERMS): array
    {
        $user = $this->userWithPermissions($permissions);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        return [$user, $employee];
    }

    private function leaveType(): LeaveType
    {
        return LeaveType::create([
            'name' => 'سنوية', 'code' => 'annual', 'is_paid' => true,
            'consumes_balance' => true, 'requires_attachment' => false, 'is_active' => true,
        ]);
    }

    private function balanceFor(Employee $employee, LeaveType $type, float $entitled, float $consumed = 0, ?int $year = null): void
    {
        LeaveBalance::create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'year' => $year ?? now()->year, 'entitled_days' => $entitled, 'consumed_days' => $consumed,
        ]);
    }

    public function test_balance_shows_my_balances(): void
    {
        [$user, $employee] = $this->linkedUser();
        $type = $this->leaveType();
        $this->balanceFor($employee, $type, 30, 5);

        $res = $this->actingAsToken($user)->getJson('/api/me/leave/balance')->assertOk();

        $this->assertEquals(25, $res->json('data.total_remaining'));
        $this->assertSame('سنوية', $res->json('data.by_type.0.type'));
    }

    public function test_submit_valid_request(): void
    {
        [$user, $employee] = $this->linkedUser();
        $type = $this->leaveType();
        $this->balanceFor($employee, $type, 30, 0, 2027);

        $res = $this->actingAsToken($user)->postJson('/api/me/leave/requests', [
            'leave_type_id' => $type->id, 'start_date' => '2027-03-01', 'end_date' => '2027-03-03',
        ])->assertStatus(201);

        $this->assertSame('pending', $res->json('data.status')); // لا اعتماد تلقائي
        $this->assertDatabaseHas('leave_requests', [
            'id' => $res->json('data.id'), 'employee_id' => $employee->id, 'status' => 'pending',
        ]);
    }

    public function test_submit_rejected_when_balance_insufficient(): void
    {
        [$user, $employee] = $this->linkedUser();
        $type = $this->leaveType();
        $this->balanceFor($employee, $type, 1, 0, 2027); // رصيد ناقص

        $this->actingAsToken($user)->postJson('/api/me/leave/requests', [
            'leave_type_id' => $type->id, 'start_date' => '2027-03-01', 'end_date' => '2027-03-05',
        ])->assertStatus(422);
    }

    public function test_cannot_submit_for_another_employee(): void
    {
        [$user, $employee] = $this->linkedUser();
        $type = $this->leaveType();
        $this->balanceFor($employee, $type, 30, 0, 2027);
        $other = Employee::factory()->create();

        // نمرّر employee_id لموظف آخر — يجب أن يُتجاهَل ويُنشأ الطلب لي.
        $res = $this->actingAsToken($user)->postJson('/api/me/leave/requests', [
            'employee_id' => $other->id,
            'leave_type_id' => $type->id, 'start_date' => '2027-03-01', 'end_date' => '2027-03-03',
        ])->assertStatus(201);

        $this->assertDatabaseHas('leave_requests', ['id' => $res->json('data.id'), 'employee_id' => $employee->id]);
        $this->assertDatabaseMissing('leave_requests', ['employee_id' => $other->id]);
    }

    public function test_lists_only_my_requests(): void
    {
        [$user, $employee] = $this->linkedUser();
        $type = $this->leaveType();
        $this->balanceFor($employee, $type, 30, 0, 2027);
        $this->actingAsToken($user)->postJson('/api/me/leave/requests', [
            'leave_type_id' => $type->id, 'start_date' => '2027-03-01', 'end_date' => '2027-03-02',
        ])->assertStatus(201);

        $res = $this->actingAsToken($user)->getJson('/api/me/leave/requests')->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('pending', $res->json('data.0.status'));
    }

    public function test_view_permission_does_not_allow_submit(): void
    {
        [$user, $employee] = $this->linkedUser(['leave.view_own']); // بلا leave.request_own
        $type = $this->leaveType();
        $this->balanceFor($employee, $type, 30, 0, 2027);

        $this->actingAsToken($user)->getJson('/api/me/leave/balance')->assertOk();
        $this->actingAsToken($user)->postJson('/api/me/leave/requests', [
            'leave_type_id' => $type->id, 'start_date' => '2027-03-01', 'end_date' => '2027-03-02',
        ])->assertStatus(403);
    }

    public function test_forbidden_for_unlinked_user(): void
    {
        $user = $this->userWithPermissions(self::PERMS); // بلا موظف مرتبط

        $this->actingAsToken($user)->getJson('/api/me/leave/balance')
            ->assertStatus(403)->assertJsonPath('errors.code', 'NO_LINKED_EMPLOYEE');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/me/leave/balance')->assertStatus(401);
    }
}
