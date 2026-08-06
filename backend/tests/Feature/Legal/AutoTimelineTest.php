<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\CaseTimelineEvent;
use Modules\Legal\Models\Client;
use Modules\Legal\Models\Hearing;
use Modules\Legal\Models\LegalCase;
use RuntimeException;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * الأحداث التلقائية للخط الزمني (LG-2) — كل إجراء مجال يوثّق نفسه بمفتاح آلي (event_type).
 */
class AutoTimelineTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_creating_case_logs_case_created(): void
    {
        $user = $this->userWithPermissions(['cases.create']);
        $client = Client::factory()->create();

        $id = $this->actingAsToken($user)
            ->postJson('/api/cases', ['internal_number' => 'AC-1', 'title' => 'قضية', 'client_id' => $client->id])
            ->assertCreated()->json('data.id');

        $this->assertDatabaseHas('case_timeline_events', ['case_id' => $id, 'event_type' => 'case_created']);
    }

    public function test_assigning_lawyer_logs_lawyer_assigned(): void
    {
        $user = $this->userWithPermissions(['cases.assign', 'cases.view_all']);
        $case = LegalCase::factory()->create();
        $lawyer = Employee::factory()->create();

        $this->actingAsToken($user)
            ->postJson("/api/cases/{$case->id}/assign", ['employee_id' => $lawyer->id, 'role' => 'support'])
            ->assertCreated();

        $this->assertDatabaseHas('case_timeline_events', ['case_id' => $case->id, 'event_type' => 'lawyer_assigned']);
    }

    public function test_closing_case_logs_case_closed(): void
    {
        $user = $this->userWithPermissions(['cases.close', 'cases.view_all']);
        $case = LegalCase::factory()->create(['status' => 'open']);

        $this->actingAsToken($user)->postJson("/api/cases/{$case->id}/close")->assertOk();

        $this->assertDatabaseHas('case_timeline_events', ['case_id' => $case->id, 'event_type' => 'case_closed']);
    }

    public function test_adding_hearing_logs_hearing_scheduled(): void
    {
        $user = $this->userWithPermissions(['hearings.manage']);
        $case = LegalCase::factory()->create();

        $this->actingAsToken($user)
            ->postJson("/api/cases/{$case->id}/hearings", ['scheduled_at' => now()->addDays(5)->toDateTimeString()])
            ->assertCreated();

        $this->assertDatabaseHas('case_timeline_events', ['case_id' => $case->id, 'event_type' => 'hearing_scheduled']);
    }

    public function test_postponing_hearing_logs_hearing_postponed(): void
    {
        $user = $this->userWithPermissions(['hearings.manage']);
        $case = LegalCase::factory()->create();
        $hearing = Hearing::factory()->create(['case_id' => $case->id, 'scheduled_at' => now()->addDays(2)]);

        $this->actingAsToken($user)
            ->postJson("/api/hearings/{$hearing->id}/postpone", ['scheduled_at' => now()->addDays(9)->toDateTimeString(), 'postponed_reason' => 'طلب الخصم'])
            ->assertCreated();

        $this->assertDatabaseHas('case_timeline_events', ['case_id' => $case->id, 'event_type' => 'hearing_postponed']);
    }

    public function test_automatic_events_remain_append_only(): void
    {
        $user = $this->userWithPermissions(['cases.close', 'cases.view_all']);
        $case = LegalCase::factory()->create();
        $this->actingAsToken($user)->postJson("/api/cases/{$case->id}/close")->assertOk();

        $event = CaseTimelineEvent::where('case_id', $case->id)->where('event_type', 'case_closed')->firstOrFail();

        $this->expectException(RuntimeException::class);
        $event->update(['title' => 'محاولة تعديل']);
    }
}
