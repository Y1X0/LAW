<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Position;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class PositionManagementTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_can_create_and_list_positions(): void
    {
        $admin = $this->userWithPermissions(['employees.update', 'employees.view']);

        $this->actingAsToken($admin)->postJson('/api/positions', ['title' => 'محامٍ أول'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'محامٍ أول');

        $this->assertDatabaseHas('positions', ['title' => 'محامٍ أول']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'position_created']);

        $this->actingAsToken($admin)->getJson('/api/positions')->assertOk();
    }

    public function test_can_update_and_delete_position(): void
    {
        $admin = $this->userWithPermissions(['employees.update']);
        $position = Position::create(['title' => 'قديم']);

        $this->actingAsToken($admin)->putJson("/api/positions/{$position->id}", ['title' => 'محدّث'])
            ->assertOk()->assertJsonPath('data.title', 'محدّث');

        $this->actingAsToken($admin)->deleteJson("/api/positions/{$position->id}")->assertOk();
        $this->assertDatabaseMissing('positions', ['id' => $position->id]);
    }

    public function test_position_write_requires_permission_and_audits_denial(): void
    {
        $viewer = $this->userWithPermissions(['employees.view']); // لا يملك update

        $this->actingAsToken($viewer)->postJson('/api/positions', ['title' => 'x'])
            ->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', ['action' => 'permission_denied', 'user_id' => $viewer->id]);
    }

    public function test_position_title_is_required(): void
    {
        $admin = $this->userWithPermissions(['employees.update']);

        $this->actingAsToken($admin)->postJson('/api/positions', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_employee_can_be_linked_to_position(): void
    {
        $admin = $this->userWithPermissions(['employees.update']);
        $employee = Employee::factory()->create();
        $position = Position::create(['title' => 'مستشار']);

        $this->actingAsToken($admin)->putJson("/api/employees/{$employee->id}", ['position_id' => $position->id])
            ->assertOk();

        $this->assertSame($position->id, $employee->fresh()->position->id);
    }

    public function test_invalid_position_link_is_rejected(): void
    {
        $admin = $this->userWithPermissions(['employees.update']);
        $employee = Employee::factory()->create();

        $this->actingAsToken($admin)->putJson("/api/employees/{$employee->id}", ['position_id' => 999999])
            ->assertStatus(422);
    }

    public function test_positions_require_authentication(): void
    {
        $this->getJson('/api/positions')->assertStatus(401);
        // للتأكد أن المستخدم غير المصرّح له يُمنع
        $user = User::factory()->create();
        $this->actingAsToken($user)->getJson('/api/positions')->assertStatus(403);
    }
}
