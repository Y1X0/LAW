<?php

namespace Modules\Notifications\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Notifications\Mail\NotificationMail;
use Modules\Notifications\Models\Notification;

/**
 * خدمة الإشعارات (Notifications / Phase 8) — المصدر الوحيد لإنشاء الإشعارات داخل النظام.
 *
 * تستدعي الوحدات المُنتِجة `emit(...)` بجوار `recordAudit(...)` عند وقوع الأحداث (PR-2)،
 * فيبقى منطق «من يُشعَر ومتى» في الخدمات، وهذه الطبقة تتكفّل بالتخزين فقط. قناة البريد
 * (B2 · PR-3) تُبنى فوق نفس المسار: بعد كتابة الإشعار داخل النظام، إن كان نوعه ضمن القائمة
 * البيضاء والمستخدمُ نشطاً وبريدُه غير فارغ، تُرسَل نسخة بريدية. البريد أثرٌ جانبي: فشله لا
 * يكسر الطلب ولا يمنع إنشاء الإشعار الداخلي (الذي يبقى مصدر الحقيقة).
 */
class NotificationService
{
    public function emit(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $relatedType = null,
        ?int $relatedId = null,
    ): Notification {
        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ]);

        $this->maybeEmail($notification);

        return $notification;
    }

    /**
     * يرسل نسخة بريدية للإشعار إن استوفى كل الشروط، وإلا يتجاهله بصمت. best-effort تماماً:
     * أي استثناء (SMTP معطّل، مستخدم محذوف...) يُسجَّل تحذيراً فقط ولا يُفشِل الطلب.
     *
     * الشروط (وفق التصميم المعتمد): النوع ضمن config('notifications.email_types')، والمستخدم
     * موجود ونشط (status = active)، وبريده غير فارغ. ما عدا ذلك: لا بريد، بلا خطأ.
     */
    private function maybeEmail(Notification $notification): void
    {
        if (! in_array($notification->type, (array) config('notifications.email_types', []), true)) {
            return;
        }

        try {
            $user = User::find($notification->user_id);

            if ($user === null || $user->status !== 'active' || blank($user->email)) {
                return;
            }

            Mail::to($user->email)->send(new NotificationMail($notification));
        } catch (\Throwable $e) {
            Log::warning('notification email failed', [
                'type' => $notification->type,
                'user_id' => $notification->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
