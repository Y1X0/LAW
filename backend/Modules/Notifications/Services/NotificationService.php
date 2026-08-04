<?php

namespace Modules\Notifications\Services;

use Modules\Notifications\Models\Notification;

/**
 * خدمة الإشعارات (Notifications / Phase 8) — المصدر الوحيد لإنشاء الإشعارات داخل النظام.
 *
 * تستدعي الوحدات المُنتِجة `emit(...)` بجوار `recordAudit(...)` عند وقوع الأحداث (PR-2)،
 * فيبقى منطق «من يُشعَر ومتى» في الخدمات، وهذه الطبقة تتكفّل بالتخزين فقط. قنوات إضافية
 * (بريد...) تُبنى على نفس هذا المسار لاحقاً دون تغيير المستدعين.
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
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ]);
    }
}
