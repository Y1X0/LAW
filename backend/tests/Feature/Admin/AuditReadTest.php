<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\AuditLog;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * قراءة سجلّ التدقيق (ADMIN-5) — نقطة محميّة بصلاحية audit.view، للقراءة فقط.
 */
class AuditReadTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function seedLogs(): void
    {
        AuditLog::create(['user_id' => null, 'action' => 'login', 'auditable_type' => User::class, 'auditable_id' => 1]);
        AuditLog::create(['user_id' => null, 'action' => 'user_created', 'auditable_type' => User::class, 'auditable_id' => 2]);
    }

    public function test_lists_audit_events_with_pagination(): void
    {
        $viewer = $this->userWithPermissions(['audit.view']);
        $this->seedLogs();

        $this->actingAsToken($viewer)->getJson('/api/admin/audit?per_page=10')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'action', 'user', 'auditable_type', 'auditable_id', 'ip_address', 'created_at']],
                'meta' => ['page', 'per_page', 'total', 'total_pages'],
            ]);
    }

    public function test_filters_by_action(): void
    {
        $viewer = $this->userWithPermissions(['audit.view']);
        $this->seedLogs();

        $res = $this->actingAsToken($viewer)->getJson('/api/admin/audit?action=user_created')
            ->assertOk()->json('data');

        $this->assertNotEmpty($res);
        foreach ($res as $row) {
            $this->assertStringContainsString('user_created', $row['action']);
        }
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/admin/audit')->assertUnauthorized();
    }

    public function test_forbidden_without_audit_view_permission(): void
    {
        $noPerm = $this->userWithPermissions(['users.manage']); // صلاحية أخرى لا تكفي

        $this->actingAsToken($noPerm)->getJson('/api/admin/audit')->assertForbidden();
    }
}
