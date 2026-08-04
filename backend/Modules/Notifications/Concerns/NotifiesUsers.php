<?php

namespace Modules\Notifications\Concerns;

use Illuminate\Support\Facades\Log;
use Modules\Notifications\Services\NotificationService;

/**
 * مساعد إطلاق الإشعارات من الخدمات — best-effort بجوار recordAudit.
 *
 * قاعدتان: (1) الإشعار أثرٌ جانبي لا جزء من العملية — فشله يُسجَّل فقط ولا يكسر العملية
 * الأساسية (المستخدم أكمل إجراءه بنجاح حتى لو تعذّر الإشعار). (2) `userId = null` (موظف
 * بلا حساب دخول مثلاً) يعني «لا مستلِم» فلا شيء يُرسَل. المستدعي يتكفّل بمنطق «من ومتى».
 */
trait NotifiesUsers
{
    protected function notify(
        ?int $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $relatedType = null,
        ?int $relatedId = null,
    ): void {
        if ($userId === null) {
            return;
        }

        try {
            app(NotificationService::class)->emit($userId, $type, $title, $body, $relatedType, $relatedId);
        } catch (\Throwable $e) {
            Log::warning('notification emit failed', [
                'type' => $type,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
