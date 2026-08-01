<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\AuthToken;
use Modules\Core\Models\Role;
use Modules\HR\Models\Employee;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * إدارة المستخدمين لوحدة تحكّم المنصّة (ADMIN-2) — محميّة بصلاحية users.manage.
 * تغطّي CRUD والصلاحيات والتفويض وقواعد الحماية الذاتية والربط بالموظف.
 */
class UserManagementTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** ينشئ مديراً فعّالاً يملك users.manage (عبر دور)، ويعيد المستخدم. */
    private function admin(): User
    {
        return $this->userWithPermissions(['users.manage']);
    }

    // ---------- القراءة: القائمة/البحث/الترقيم/التفاصيل ----------

    public function test_lists_users_with_pagination_meta(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create();

        $this->actingAsToken($admin)->getJson('/api/users?per_page=2')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email', 'status', 'roles', 'employee']],
                'meta' => ['page', 'per_page', 'total', 'total_pages'],
            ])
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_search_filters_users_by_name_or_email(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'زهراء المطيري', 'email' => 'zahraa@law.test']);
        User::factory()->create(['name' => 'خالد', 'email' => 'khaled@law.test']);

        $res = $this->actingAsToken($admin)->getJson('/api/users?search=zahraa')->assertOk()->json('data');

        $this->assertNotEmpty($res);
        foreach ($res as $row) {
            $this->assertStringContainsStringIgnoringCase('zahraa', $row['email'].$row['name']);
        }
    }

    public function test_shows_user_with_roles_and_linked_employee(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $target->id]);

        $this->actingAsToken($admin)->getJson("/api/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath('data.employee.id', $employee->id);
    }

    // ---------- الإنشاء والتعديل ----------

    public function test_creates_user_with_validation_and_hashes_password(): void
    {
        $admin = $this->admin();

        $this->actingAsToken($admin)->postJson('/api/users', [
            'name' => 'مستخدم جديد',
            'email' => 'new@law.test',
            'password' => 'Secret123',
        ])->assertCreated()->assertJsonPath('data.email', 'new@law.test');

        $this->assertDatabaseHas('users', ['email' => 'new@law.test', 'status' => 'active']);
        $created = User::where('email', 'new@law.test')->first();
        $this->assertNotSame('Secret123', $created->password); // مُجزّأة
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_created']);
    }

    public function test_create_rejects_duplicate_email(): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'dup@law.test']);

        $this->actingAsToken($admin)->postJson('/api/users', [
            'name' => 'x', 'email' => 'dup@law.test', 'password' => 'Secret123',
        ])->assertStatus(422)->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_create_can_assign_roles(): void
    {
        $admin = $this->admin();
        $role = Role::create(['name' => 'reviewer', 'display_name' => 'مُراجِع']);

        $this->actingAsToken($admin)->postJson('/api/users', [
            'name' => 'مع دور', 'email' => 'withrole@law.test', 'password' => 'Secret123',
            'role_ids' => [$role->id],
        ])->assertCreated()->assertJsonPath('data.roles.0.name', 'reviewer');
    }

    public function test_updates_user_profile_fields(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['name' => 'قديم']);

        $this->actingAsToken($admin)->patchJson("/api/users/{$target->id}", ['name' => 'محدّث'])
            ->assertOk()->assertJsonPath('data.name', 'محدّث');

        $this->assertDatabaseHas('audit_logs', ['action' => 'user_updated']);
    }

    // ---------- التفويض ----------

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }

    public function test_forbidden_without_users_manage_permission(): void
    {
        $noPerm = $this->userWithPermissions(['employees.view']); // صلاحية أخرى غير كافية

        $this->actingAsToken($noPerm)->getJson('/api/users')->assertForbidden();
        $this->actingAsToken($noPerm)->postJson('/api/users', [
            'name' => 'x', 'email' => 'y@law.test', 'password' => 'Secret123',
        ])->assertForbidden();
    }

    // ---------- التعطيل/التفعيل + الحماية الذاتية ----------

    public function test_disable_then_enable_toggles_status_and_revokes_tokens(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['status' => 'active']);
        // جلسة قائمة للهدف يجب أن تُبطَل عند التعطيل.
        $token = AuthToken::create([
            'user_id' => $target->id,
            'access_token_hash' => hash('sha256', 'x'),
            'refresh_token_hash' => hash('sha256', 'y'),
            'access_expires_at' => now()->addMinutes(15),
            'refresh_expires_at' => now()->addDays(14),
        ]);

        $this->actingAsToken($admin)->postJson("/api/users/{$target->id}/disable")
            ->assertOk()->assertJsonPath('data.status', 'suspended');
        $this->assertNotNull($token->fresh()->revoked_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_disabled']);

        $this->actingAsToken($admin)->postJson("/api/users/{$target->id}/enable")
            ->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_enabled']);
    }

    public function test_cannot_disable_self_when_not_last_admin(): void
    {
        $admin = $this->admin();
        $this->admin(); // مدير آخر موجود → الهدف ليس آخر مدير

        $this->actingAsToken($admin)->postJson("/api/users/{$admin->id}/disable")
            ->assertStatus(422)->assertJsonPath('errors.code', 'SELF_DISABLE_FORBIDDEN');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'status' => 'active']);
    }

    public function test_cannot_disable_last_active_admin(): void
    {
        $admin = $this->admin(); // المدير الوحيد

        $this->actingAsToken($admin)->postJson("/api/users/{$admin->id}/disable")
            ->assertStatus(422)->assertJsonPath('errors.code', 'LAST_ADMIN_PROTECTED');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'status' => 'active']);
    }

    public function test_can_disable_another_admin_while_one_remains(): void
    {
        $admin = $this->admin();
        $other = $this->admin();

        $this->actingAsToken($admin)->postJson("/api/users/{$other->id}/disable")
            ->assertOk()->assertJsonPath('data.status', 'suspended');
    }

    // ---------- إعادة تعيين كلمة المرور ----------

    public function test_reset_password_sets_new_hash_and_revokes_tokens(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $old = $target->password;
        $token = AuthToken::create([
            'user_id' => $target->id,
            'access_token_hash' => hash('sha256', 'a'),
            'refresh_token_hash' => hash('sha256', 'b'),
            'access_expires_at' => now()->addMinutes(15),
            'refresh_expires_at' => now()->addDays(14),
        ]);

        $this->actingAsToken($admin)->postJson("/api/users/{$target->id}/reset-password", [
            'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ])->assertOk();

        $this->assertNotSame($old, $target->fresh()->password);
        $this->assertNotNull($token->fresh()->revoked_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_password_reset']);
    }

    public function test_reset_password_requires_confirmation(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->actingAsToken($admin)->postJson("/api/users/{$target->id}/reset-password", [
            'password' => 'NewPass123', 'password_confirmation' => 'mismatch',
        ])->assertStatus(422);
    }

    // ---------- الربط/فكّ الربط بالموظف ----------

    public function test_links_and_unlinks_employee(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => null]);

        $this->actingAsToken($admin)->postJson("/api/users/{$target->id}/employee", [
            'employee_id' => $employee->id,
        ])->assertOk()->assertJsonPath('data.employee.id', $employee->id);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'user_id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employee_identity_linked']);

        $this->actingAsToken($admin)->deleteJson("/api/users/{$target->id}/employee")
            ->assertOk()->assertJsonPath('data.employee', null);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'user_id' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employee_identity_unlinked']);
    }

    public function test_link_rejects_employee_already_linked_to_another_account(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $otherUser = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAsToken($admin)->postJson("/api/users/{$target->id}/employee", [
            'employee_id' => $employee->id,
        ])->assertStatus(422);
    }
}
