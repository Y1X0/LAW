# Notifications (Phase 8)

إشعارات داخل النظام لكل مستخدم. المصدر الوحيد للإنشاء هو `NotificationService::emit(...)`
الذي تستدعيه الوحدات المُنتِجة عند وقوع الأحداث (بجوار `recordAudit`) — يبقى منطق
«من يُشعَر ومتى» في الخدمات، وهذه الوحدة تتكفّل بالتخزين والعرض.

## البنية

- **الجدول:** `user_notifications` (مفتاح صحيح + `user_id` صريح — لا يستخدم جدول Laravel
  القياسي متعدّد الأشكال تفادياً للتعارض مع `Notifiable::notifications()`).
- **النموذج:** `Models\Notification` (`read_at = null` ⇒ غير مقروء).
- **الخدمة:** `Services\NotificationService::emit($userId, $type, $title, $body?, $relatedType?, $relatedId?)`.

## النقاط (كلها خلف `notifications.view_own`، منطوقة على المستخدم الحالي)

| الطريقة | المسار | الوظيفة |
|--------|-------|---------|
| GET | `/api/notifications` | قائمة إشعاراتي (الأحدث أولاً) + `meta.unread` · فلتر `?unread=1` |
| GET | `/api/notifications/unread-count` | عدّاد غير المقروء (للجرس) |
| PATCH | `/api/notifications/{id}/read` | تعليم إشعاري كمقروء (idempotent) |
| PATCH | `/api/notifications/read-all` | تعليم كل إشعاراتي كمقروءة |

إشعار مستخدمٍ آخر يُعاد **404** (لا يُكشف وجوده).

## النطاق (Phase 8)

- **PR-1 (هذا):** الأساس — تخزين + قراءة ذاتية + تعليم كمقروء.
- **PR-2:** ربط مُطلِقات الأحداث في الخدمات المُنتِجة (إجازة/مهمة/دفعة...).
- **PR-3:** قناة البريد على نفس مسار `emit` (يتطلّب ضبط SMTP في الإنتاج).
- **PR-4:** واجهة الجرس/صندوق الوارد.
- **مؤجَّل:** التذكيرات المجدولة (جلسة غداً/مهمة متأخّرة) — تحتاج Render cron.
