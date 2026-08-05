<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Notifications\Mail\NotificationMail;
use Modules\Notifications\Services\NotificationService;
use Tests\TestCase;

/**
 * قناة بريد الإشعارات (Notifications / B2 · PR-3).
 *
 * يؤكّد أن: الأنواع المُدرَجة في القائمة البيضاء تُرسَل بريداً لمستخدم نشط؛ الأنواع خارجها
 * (المهام) لا تُرسَل؛ المستخدم غير النشط أو الفارغ البريد لا يُراسَل؛ وفي كل الحالات يُنشأ
 * الإشعار الداخلي (مصدر الحقيقة) — فالبريد أثرٌ جانبي لا يمنع الكتابة ولا يكسر العملية.
 */
class NotificationEmailTest extends TestCase
{
    use RefreshDatabase;

    private function emit(User $user, string $type): void
    {
        app(NotificationService::class)->emit($user->id, $type, 'عنوان الإشعار', 'نص الإشعار');
    }

    public function test_whitelisted_type_sends_email_to_active_user(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'active@firm.test']);

        $this->emit($user, 'leave_approved');

        Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->notification->type === 'leave_approved';
        });
        // الإشعار الداخلي يُنشأ دائماً.
        $this->assertDatabaseHas('user_notifications', ['user_id' => $user->id, 'type' => 'leave_approved']);
    }

    /** كل أنواع القائمة البيضاء تُرسِل بريداً (حراسة تراجُع الإعداد). */
    public function test_every_whitelisted_type_sends_email(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        foreach (config('notifications.email_types') as $type) {
            $this->emit($user, $type);
        }

        Mail::assertSent(NotificationMail::class, count(config('notifications.email_types')));
    }

    public function test_task_types_stay_in_app_only(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->emit($user, 'task_assigned');
        $this->emit($user, 'task_completed');

        Mail::assertNotSent(NotificationMail::class);
        // مع ذلك، الإشعار الداخلي يُنشأ لكليهما.
        $this->assertDatabaseHas('user_notifications', ['user_id' => $user->id, 'type' => 'task_assigned']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $user->id, 'type' => 'task_completed']);
    }

    public function test_suspended_user_is_not_emailed_but_still_notified(): void
    {
        Mail::fake();
        $user = User::factory()->create(['status' => 'suspended']);

        $this->emit($user, 'invoice_overdue');

        Mail::assertNotSent(NotificationMail::class);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $user->id, 'type' => 'invoice_overdue']);
    }

    public function test_blank_email_user_is_not_emailed_but_still_notified(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => '']);

        $this->emit($user, 'hearing_upcoming');

        Mail::assertNotSent(NotificationMail::class);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $user->id, 'type' => 'hearing_upcoming']);
    }
}
