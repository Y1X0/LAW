<?php

namespace Tests\Feature\SelfService;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class MyAttendanceTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** @return array{0:User, 1:Employee} */
    private function linkedUser(array $permissions = ['attendance.view_own']): array
    {
        $user = $this->userWithPermissions($permissions);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        return [$user, $employee];
    }

    private function record(Employee $employee, string $date, string $status = 'present', int $late = 0): void
    {
        AttendanceRecord::create([
            'employee_id' => $employee->id, 'work_date' => $date,
            'status' => $status, 'late_minutes' => $late, 'worked_minutes' => 480,
        ]);
    }

    public function test_lists_my_attendance(): void
    {
        [$user, $employee] = $this->linkedUser();
        $this->record($employee, '2027-03-01', 'present');
        $this->record($employee, '2027-03-02', 'late', 20);

        $res = $this->actingAsToken($user)->getJson('/api/me/attendance')->assertOk();

        $this->assertCount(2, $res->json('data'));
        // الأحدث أولاً
        $this->assertSame('2027-03-02', $res->json('data.0.date'));
        $this->assertSame('late', $res->json('data.0.status'));
        $this->assertEquals(20, $res->json('data.0.late_minutes'));
    }

    public function test_filters_by_date_range(): void
    {
        [$user, $employee] = $this->linkedUser();
        $this->record($employee, '2027-03-01');
        $this->record($employee, '2027-03-15');
        $this->record($employee, '2027-04-01');

        $res = $this->actingAsToken($user)->getJson('/api/me/attendance?from=2027-03-10&to=2027-03-31')->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertSame('2027-03-15', $res->json('data.0.date'));
    }

    public function test_employee_cannot_see_another_employees_attendance(): void
    {
        [$user, $employee] = $this->linkedUser();
        $this->record($employee, '2027-03-01', 'present');

        // موظف آخر بسجلّات — يجب ألا تظهر في سجلّي.
        $other = Employee::factory()->create();
        $this->record($other, '2027-03-02', 'absent');
        $this->record($other, '2027-03-03', 'absent');

        $res = $this->actingAsToken($user)->getJson('/api/me/attendance')->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertSame('2027-03-01', $res->json('data.0.date'));
    }

    public function test_requires_permission(): void
    {
        [$user] = $this->linkedUser([]); // موظف مرتبط بلا صلاحية

        $this->actingAsToken($user)->getJson('/api/me/attendance')->assertStatus(403);
    }

    public function test_forbidden_for_unlinked_user(): void
    {
        $user = $this->userWithPermissions(['attendance.view_own']); // بلا موظف مرتبط

        $this->actingAsToken($user)->getJson('/api/me/attendance')
            ->assertStatus(403)->assertJsonPath('errors.code', 'NO_LINKED_EMPLOYEE');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/me/attendance')->assertStatus(401);
    }
}
