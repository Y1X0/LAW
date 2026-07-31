<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\CaseAssignment;
use Modules\Legal\Models\CaseTimelineEvent;
use Modules\Legal\Models\LegalCase;
use RuntimeException;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class TimelineTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    /** محامٍ (view_own) مرتبط بموظف، مع قضية مسندة. */
    private function lawyerWithCase(string $internal): array
    {
        $user = $this->userWithPermissions(['cases.view_own']);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $case = LegalCase::factory()->create(['internal_number' => $internal, 'responsible_lawyer_id' => $employee->id]);
        CaseAssignment::create(['case_id' => $case->id, 'employee_id' => $employee->id, 'role' => 'lead']);

        return [$user, $case];
    }

    public function test_can_append_timeline_event(): void
    {
        $manager = $this->userWithPermissions(['cases.update']);
        $case = LegalCase::factory()->create();

        $this->actingAsToken($manager)
            ->postJson("/api/cases/{$case->id}/timeline", [
                'title' => 'تقديم مذكرة دفاع',
                'event_type' => 'memo',
                'event_date' => now()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'تقديم مذكرة دفاع');

        $this->assertDatabaseHas('case_timeline_events', ['case_id' => $case->id, 'title' => 'تقديم مذكرة دفاع', 'created_by' => $manager->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'case_timeline_event_added']);
    }

    public function test_timeline_event_cannot_be_updated_at_model_level(): void
    {
        $event = CaseTimelineEvent::factory()->create();

        $this->expectException(RuntimeException::class);
        $event->update(['title' => 'محاولة تعديل']);
    }

    public function test_timeline_event_cannot_be_deleted_at_model_level(): void
    {
        $event = CaseTimelineEvent::factory()->create();

        $this->expectException(RuntimeException::class);
        $event->delete();
    }

    public function test_no_http_endpoint_modifies_a_timeline_event(): void
    {
        $manager = $this->userWithPermissions(['cases.update', 'cases.view_all']);
        $event = CaseTimelineEvent::factory()->create();

        // لا مسارات PUT/DELETE على أحداث الخط الزمني (append-only).
        $this->actingAsToken($manager)->putJson("/api/cases/{$event->case_id}/timeline/{$event->id}", ['title' => 'x'])->assertStatus(404);
        $this->actingAsToken($manager)->deleteJson("/api/cases/{$event->case_id}/timeline/{$event->id}")->assertStatus(404);
        $this->assertDatabaseHas('case_timeline_events', ['id' => $event->id, 'title' => $event->title]);
    }

    public function test_lawyer_sees_only_own_case_timeline(): void
    {
        [$userA, $caseA] = $this->lawyerWithCase('A-1');
        [, $caseB] = $this->lawyerWithCase('B-1');
        CaseTimelineEvent::factory()->create(['case_id' => $caseA->id, 'title' => 'حدث A']);
        CaseTimelineEvent::factory()->create(['case_id' => $caseB->id, 'title' => 'حدث B']);

        $this->actingAsToken($userA)->getJson("/api/cases/{$caseA->id}/timeline")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'حدث A');

        $this->actingAsToken($userA)->getJson("/api/cases/{$caseB->id}/timeline")
            ->assertStatus(403)
            ->assertJsonPath('errors.code', 'FORBIDDEN');
    }

    public function test_admin_sees_any_case_timeline(): void
    {
        $case = LegalCase::factory()->create();
        CaseTimelineEvent::factory()->create(['case_id' => $case->id]);
        $admin = $this->userWithPermissions(['cases.view_all']);

        $this->actingAsToken($admin)->getJson("/api/cases/{$case->id}/timeline")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_append_requires_update_permission(): void
    {
        $viewer = $this->userWithPermissions(['cases.view_all']);
        $case = LegalCase::factory()->create();

        $this->actingAsToken($viewer)
            ->postJson("/api/cases/{$case->id}/timeline", ['title' => 'x', 'event_date' => now()->toDateString()])
            ->assertStatus(403);
    }

    public function test_append_validates_input(): void
    {
        $manager = $this->userWithPermissions(['cases.update']);
        $case = LegalCase::factory()->create();

        $this->actingAsToken($manager)
            ->postJson("/api/cases/{$case->id}/timeline", ['title' => '', 'event_date' => 'not-a-date'])
            ->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $case = LegalCase::factory()->create();
        $this->getJson("/api/cases/{$case->id}/timeline")->assertStatus(401);
    }
}
