<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Notifications\Models\Notification;
use Modules\Notifications\Services\NotificationService;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * إشعارات المستخدم داخل النظام (Phase 8 · PR-1) — العزل الذاتي، عدّاد غير المقروء،
 * التعليم كمقروء (فردي/شامل)، وحرّاس الصلاحية/المصادقة.
 */
class NotificationTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function actor(): User
    {
        return $this->userWithPermissions(['notifications.view_own']);
    }

    public function test_service_emit_persists_notification(): void
    {
        $user = User::factory()->create();

        $n = app(NotificationService::class)->emit(
            $user->id, 'payment_recorded', 'دفعة سُجّلت', 'تم تسجيل دفعة 400 على فاتورتك', 'Invoice', 12,
        );

        $this->assertDatabaseHas('user_notifications', [
            'id' => $n->id, 'user_id' => $user->id, 'type' => 'payment_recorded',
            'related_type' => 'Invoice', 'related_id' => 12, 'read_at' => null,
        ]);
    }

    public function test_user_sees_only_own_notifications_with_unread_count(): void
    {
        $user = $this->actor();
        $other = User::factory()->create();

        Notification::factory()->count(2)->create(['user_id' => $user->id]);
        Notification::factory()->read()->create(['user_id' => $user->id]);
        Notification::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->actingAsToken($user)->getJson('/api/notifications')->assertOk();

        // 3 خاصته فقط (لا إشعارات الآخر)، وعدّاد غير المقروء = 2.
        $response->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.unread', 2);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_unread_filter_returns_only_unread(): void
    {
        $user = $this->actor();
        Notification::factory()->count(2)->create(['user_id' => $user->id]);
        Notification::factory()->read()->create(['user_id' => $user->id]);

        $response = $this->actingAsToken($user)->getJson('/api/notifications?unread=1')->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_unread_count_endpoint(): void
    {
        $user = $this->actor();
        Notification::factory()->count(4)->create(['user_id' => $user->id]);
        Notification::factory()->read()->create(['user_id' => $user->id]);

        $this->actingAsToken($user)->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread', 4);
    }

    public function test_mark_single_read_is_idempotent(): void
    {
        $user = $this->actor();
        $n = Notification::factory()->create(['user_id' => $user->id]);

        $this->actingAsToken($user)->patchJson("/api/notifications/{$n->id}/read")->assertOk();
        $this->assertNotNull($n->fresh()->read_at);

        // إعادة الطلب لا تُغيّر الوقت المُسجَّل (idempotent).
        $firstReadAt = $n->fresh()->read_at;
        $this->actingAsToken($user)->patchJson("/api/notifications/{$n->id}/read")->assertOk();
        $this->assertEquals($firstReadAt->toIso8601String(), $n->fresh()->read_at->toIso8601String());
    }

    public function test_mark_all_read(): void
    {
        $user = $this->actor();
        Notification::factory()->count(3)->create(['user_id' => $user->id]);

        $this->actingAsToken($user)->patchJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.marked', 3);

        $this->assertSame(0, Notification::query()->where('user_id', $user->id)->unread()->count());
    }

    public function test_cannot_mark_another_users_notification(): void
    {
        $user = $this->actor();
        $other = User::factory()->create();
        $n = Notification::factory()->create(['user_id' => $other->id]);

        // 404 (لا يُكشف وجود إشعار الغير)، ويبقى غير مقروء.
        $this->actingAsToken($user)->patchJson("/api/notifications/{$n->id}/read")->assertStatus(404);
        $this->assertNull($n->fresh()->read_at);
    }

    public function test_requires_notifications_permission(): void
    {
        $user = $this->userWithPermissions(['invoices.view']);
        $this->actingAsToken($user)->getJson('/api/notifications')->assertStatus(403);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/notifications')->assertStatus(401);
    }
}
