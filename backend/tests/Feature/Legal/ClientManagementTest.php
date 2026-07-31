<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Legal\Models\Client;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'شركة المستقبل للتجارة',
            'type' => 'company',
            'phone' => '0790000000',
            'email' => 'info@future.example',
            'national_id' => '2001234567',
            'address' => 'عمّان',
        ], $overrides);
    }

    public function test_can_create_client(): void
    {
        $admin = $this->userWithPermissions(['clients.create']);

        $this->actingAsToken($admin)
            ->postJson('/api/clients', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'شركة المستقبل للتجارة')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('clients', ['name' => 'شركة المستقبل للتجارة', 'created_by' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client_created']);
    }

    public function test_can_list_and_show_clients(): void
    {
        $viewer = $this->userWithPermissions(['clients.view']);
        $client = Client::factory()->create();

        $this->actingAsToken($viewer)->getJson('/api/clients')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['page', 'per_page', 'total', 'total_pages'], 'errors']);

        $this->actingAsToken($viewer)->getJson("/api/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $client->id);
    }

    public function test_can_update_client(): void
    {
        $editor = $this->userWithPermissions(['clients.update']);
        $client = Client::factory()->create(['name' => 'قديم']);

        $this->actingAsToken($editor)->putJson("/api/clients/{$client->id}", ['name' => 'محدّث'])
            ->assertOk()
            ->assertJsonPath('data.name', 'محدّث');

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'محدّث']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client_updated']);
    }

    public function test_can_deactivate_and_reactivate_client(): void
    {
        $editor = $this->userWithPermissions(['clients.update']);
        $client = Client::factory()->create();

        $this->actingAsToken($editor)->patchJson("/api/clients/{$client->id}/status", ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client_status_changed']);

        $this->actingAsToken($editor)->patchJson("/api/clients/{$client->id}/status", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_list_hides_inactive_by_default_but_can_include(): void
    {
        $viewer = $this->userWithPermissions(['clients.view']);
        Client::factory()->create(['name' => 'نشط']);
        Client::factory()->inactive()->create(['name' => 'معطّل']);

        $this->actingAsToken($viewer)->getJson('/api/clients')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAsToken($viewer)->getJson('/api/clients?include_inactive=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_no_hard_delete_endpoint(): void
    {
        $editor = $this->userWithPermissions(['clients.update', 'clients.view']);
        $client = Client::factory()->create();

        // لا يوجد مسار DELETE — المتصفّح يحصل على 405، والسجلّ يبقى قائماً.
        $this->actingAsToken($editor)->deleteJson("/api/clients/{$client->id}")
            ->assertStatus(405);

        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }

    public function test_status_change_rejects_invalid_value(): void
    {
        $editor = $this->userWithPermissions(['clients.update']);
        $client = Client::factory()->create();

        $this->actingAsToken($editor)->patchJson("/api/clients/{$client->id}/status", ['status' => 'deleted'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_create_validates_input(): void
    {
        $admin = $this->userWithPermissions(['clients.create']);

        $this->actingAsToken($admin)
            ->postJson('/api/clients', $this->payload(['name' => '', 'type' => 'unknown', 'email' => 'not-an-email']))
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_create_requires_permission(): void
    {
        $viewer = $this->userWithPermissions(['clients.view']);

        $this->actingAsToken($viewer)
            ->postJson('/api/clients', $this->payload())
            ->assertStatus(403);
    }

    public function test_routes_require_authentication(): void
    {
        $this->getJson('/api/clients')->assertStatus(401);
        $this->postJson('/api/clients', $this->payload())->assertStatus(401);
    }
}
