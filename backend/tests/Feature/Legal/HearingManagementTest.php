<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Legal\Models\Hearing;
use Modules\Legal\Models\LegalCase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class HearingManagementTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_can_create_hearing(): void
    {
        $manager = $this->userWithPermissions(['hearings.manage']);
        $case = LegalCase::factory()->create();

        $this->actingAsToken($manager)
            ->postJson("/api/cases/{$case->id}/hearings", [
                'scheduled_at' => now()->addDays(10)->toDateTimeString(),
                'type' => 'مرافعة',
                'location' => 'قاعة 2',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.case_id', $case->id);

        $this->assertDatabaseHas('hearings', ['case_id' => $case->id, 'type' => 'مرافعة', 'created_by' => $manager->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'hearing_created']);
    }

    public function test_can_update_scheduled_hearing(): void
    {
        $manager = $this->userWithPermissions(['hearings.manage']);
        $hearing = Hearing::factory()->create(['location' => 'قديم']);

        $this->actingAsToken($manager)->putJson("/api/hearings/{$hearing->id}", ['location' => 'قاعة 5'])
            ->assertOk()
            ->assertJsonPath('data.location', 'قاعة 5');

        $this->assertDatabaseHas('audit_logs', ['action' => 'hearing_updated']);
    }

    public function test_cannot_modify_held_hearing(): void
    {
        $manager = $this->userWithPermissions(['hearings.manage']);
        $hearing = Hearing::factory()->past()->create(); // status = held

        $this->actingAsToken($manager)->putJson("/api/hearings/{$hearing->id}", ['location' => 'محاولة'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_postpone_preserves_old_and_creates_new(): void
    {
        $manager = $this->userWithPermissions(['hearings.manage']);
        $case = LegalCase::factory()->create();
        $hearing = Hearing::factory()->create(['case_id' => $case->id, 'scheduled_at' => now()->addDays(5)]);
        $newDate = now()->addDays(20)->toDateTimeString();

        $this->actingAsToken($manager)
            ->postJson("/api/hearings/{$hearing->id}/postpone", ['scheduled_at' => $newDate, 'postponed_reason' => 'طلب الخصم'])
            ->assertCreated()
            ->assertJsonPath('data.postponed.status', 'postponed')
            ->assertJsonPath('data.new_hearing.status', 'scheduled');

        // القديمة محفوظة كـ postponed مع السبب؛ والجديدة أُنشئت (قضية واحدة، جلستان).
        $this->assertDatabaseHas('hearings', ['id' => $hearing->id, 'status' => 'postponed', 'postponed_reason' => 'طلب الخصم']);
        $this->assertDatabaseCount('hearings', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'hearing_postponed']);
    }

    public function test_cannot_postpone_held_hearing(): void
    {
        $manager = $this->userWithPermissions(['hearings.manage']);
        $hearing = Hearing::factory()->past()->create();

        $this->actingAsToken($manager)
            ->postJson("/api/hearings/{$hearing->id}/postpone", ['scheduled_at' => now()->addDays(3)->toDateTimeString(), 'postponed_reason' => 'x'])
            ->assertStatus(422);
    }

    public function test_can_cancel_hearing(): void
    {
        $manager = $this->userWithPermissions(['hearings.manage']);
        $hearing = Hearing::factory()->create();

        $this->actingAsToken($manager)->postJson("/api/hearings/{$hearing->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('audit_logs', ['action' => 'hearing_cancelled']);
    }

    public function test_scope_filters_upcoming_past_postponed(): void
    {
        $admin = $this->userWithPermissions(['cases.view_all']);
        Hearing::factory()->create(['scheduled_at' => now()->addDays(3), 'status' => 'scheduled']); // upcoming
        Hearing::factory()->create(['scheduled_at' => now()->subDays(3), 'status' => 'held']);       // past
        Hearing::factory()->postponed()->create(['scheduled_at' => now()->subDays(1)]);              // postponed

        $this->actingAsToken($admin)->getJson('/api/hearings?scope=upcoming')->assertOk()->assertJsonPath('meta.total', 1);
        $this->actingAsToken($admin)->getJson('/api/hearings?scope=past')->assertOk()->assertJsonPath('meta.total', 1);
        $this->actingAsToken($admin)->getJson('/api/hearings?scope=postponed')->assertOk()->assertJsonPath('meta.total', 1);
        $this->actingAsToken($admin)->getJson('/api/hearings')->assertOk()->assertJsonPath('meta.total', 3);
    }

    public function test_create_validates_input(): void
    {
        $manager = $this->userWithPermissions(['hearings.manage']);
        $case = LegalCase::factory()->create();

        $this->actingAsToken($manager)
            ->postJson("/api/cases/{$case->id}/hearings", ['scheduled_at' => 'not-a-date'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_create_requires_manage_permission(): void
    {
        $viewer = $this->userWithPermissions(['cases.view_all']);
        $case = LegalCase::factory()->create();

        $this->actingAsToken($viewer)
            ->postJson("/api/cases/{$case->id}/hearings", ['scheduled_at' => now()->addDay()->toDateTimeString()])
            ->assertStatus(403);
    }

    public function test_routes_require_authentication(): void
    {
        $this->getJson('/api/hearings')->assertStatus(401);
    }
}
