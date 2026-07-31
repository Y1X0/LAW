<?php

namespace Tests\Feature\Legal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Legal\Seeders\PilotDemoSeeder;
use Tests\TestCase;

/**
 * تحقّق «تجهيز الـ Pilot» (PR-3): بعد البذر، يدخل المحامي التجريبي عبر الـ API
 * الحقيقي ويرى نظاماً حيّاً (لا صفحات فارغة)، وتبويبات ملف قضيته مليئة، وخدمته
 * الذاتية مُتاحة — دون أي تجاوز للصلاحيات.
 */
class PilotDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilot_lawyer_logs_in_and_sees_a_live_system(): void
    {
        $this->seed(PilotDemoSeeder::class);

        // 1) تسجيل دخول عبر الـ API الحقيقي بالبيانات المعروفة.
        $login = $this->postJson('/api/auth/login', [
            'email' => PilotDemoSeeder::EMAIL,
            'password' => PilotDemoSeeder::PASSWORD,
        ])->assertOk();

        $token = $login->json('data.tokens.access_token');
        $this->assertNotEmpty($token);

        // 2) اللوحة غير فارغة.
        $summary = $this->withToken($token)->getJson('/api/me/legal-summary')->assertOk()->json('data');
        $this->assertGreaterThanOrEqual(1, $summary['cases']['total']);
        $this->assertNotNull($summary['next_hearing']);
        $this->assertNotEmpty($summary['recent_events']);
        $this->assertNotNull($summary['last_worklog']);

        // 3) القضايا — يرى قضيته المسندة.
        $cases = $this->withToken($token)->getJson('/api/cases')->assertOk()->json();
        $this->assertGreaterThanOrEqual(1, $cases['meta']['total']);
        $caseId = $cases['data'][0]['id'];

        // 4) تبويبات ملف القضية مليئة.
        $this->withToken($token)->getJson("/api/cases/{$caseId}")->assertOk()->assertJsonPath('data.internal_number', 'DEMO-1');
        $this->assertNotEmpty($this->withToken($token)->getJson("/api/cases/{$caseId}/parties")->assertOk()->json('data'));
        $this->assertNotEmpty($this->withToken($token)->getJson("/api/cases/{$caseId}/hearings")->assertOk()->json('data'));
        $this->assertNotEmpty($this->withToken($token)->getJson("/api/cases/{$caseId}/timeline")->assertOk()->json('data'));
        $this->assertNotEmpty($this->withToken($token)->getJson("/api/cases/{$caseId}/documents")->assertOk()->json('data'));
        $this->assertNotEmpty($this->withToken($token)->getJson("/api/cases/{$caseId}/archive-locations")->assertOk()->json('data'));
        $this->assertNotEmpty($this->withToken($token)->getJson('/api/tasks?case_id='.$caseId)->assertOk()->json('data'));

        // 5) الإنجاز اليومي مسجَّل.
        $this->assertNotEmpty($this->withToken($token)->getJson('/api/me/worklog')->assertOk()->json('data'));
    }

    public function test_pilot_lawyer_has_both_legal_and_self_service_permissions(): void
    {
        $this->seed(PilotDemoSeeder::class);
        $user = User::where('email', PilotDemoSeeder::EMAIL)->first();

        // قانوني (دور محامٍ).
        $this->assertTrue($user->hasPermission('cases.view_own'));
        $this->assertTrue($user->hasPermission('tasks.complete'));
        // خدمة ذاتية (دور موظف) — تبويبات HR تعمل.
        $this->assertTrue($user->hasPermission('leave.view_own'));
        $this->assertTrue($user->hasPermission('profile.update_own'));
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(PilotDemoSeeder::class);
        $this->seed(PilotDemoSeeder::class);

        $this->assertSame(1, User::where('email', PilotDemoSeeder::EMAIL)->count());
        $this->assertDatabaseCount('cases', 1);
    }
}
