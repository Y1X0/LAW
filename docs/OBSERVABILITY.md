# المراقبة والإبلاغ عن الأخطاء (Observability — Sentry)

الهدف: عندما يقع خطأ في الإنتاج، نعرفه فوراً ونربطه بالإصدار المنشور — بدل أن يبقى
مدفوناً في سجلّات Render المؤقّتة بلا تنبيه.

## خامل افتراضياً (Dormant until DSN)

لا يُرسَل أي شيء إلى Sentry ما لم يُضبط الـDSN — تماماً كنمط `MAIL_MAILER` (فارغ ⇒ معطّل).
لذا الكود آمن للدمج والنشر قبل توفير الـDSN؛ يبقى بلا أثر حتى تُفعّله.

- **الخلفية (Laravel):** `SENTRY_LARAVEL_DSN` فارغ ⇒ SDK معطّل.
- **الواجهة (React):** `VITE_SENTRY_DSN` فارغ ⇒ `initSentry()` يعود دون تهيئة.

## التفعيل على Render (بدون تغيير كود)

1. أنشئ مشروعَي Sentry (أو مشروعاً واحداً بمنصّتين): Laravel + React. انسخ الـDSN لكلٍّ.
2. **law-api** و **law-scheduler**: اضبط `SENTRY_LARAVEL_DSN` = DSN الخلفية (Environment → Secret).
3. **law-web**: اضبط `VITE_SENTRY_DSN` = DSN الواجهة ثم **أعد البناء** (يُحقن وقت البناء).
4. الإصدار: يُلتقَط تلقائياً من `RENDER_GIT_COMMIT` في الخلفية؛ للواجهة اضبط
   `VITE_SENTRY_RELEASE` = بصمة الـcommit عند البناء إن رغبت بربط الإصدار.

## خصوصية بيانات نظام قانوني (مضمونة بالإعداد)

الأخطاء تُرسِل **الأثر التقني والسياق فقط** — لا بيانات موكّلين/قضايا:

- `send_default_pii = false` (لا IP، لا كوكيز، لا هوية مستخدم).
- `max_request_body_size = 'none'` + مُنقٍّ `App\Support\SentryScrubber` يُزيل جسم الطلب/الاستعلام/الكوكيز ويحجب المفاتيح الحسّاسة (كلمات المرور، الرقم الوطني، الرواتب، التوكنات).
- `sql_bindings = false` (لا قيم بارامترات SQL في breadcrumbs).
- الواجهة: `sendDefaultPii = false` + `beforeSend` يُزيل `user`/`request.data`/الكوكيز.
- استثناءات متوقّعة (مصادقة/تحقّق/صلاحيات/404) **لا** تُبلَّغ (ضجيج لا أعطال).

## ملاحظة CSP

سياسة CSP الحالية (Report-Only) تسمح `connect-src 'self' https:` فيمرّ إرسال Sentry.
عند ترقية CSP إلى الفرض (Content-Security-Policy)، أضِف نطاق ingest الخاص بـSentry
صراحةً إلى `connect-src`.

## حدّ الخطأ في الواجهة (Error Boundary)

`ErrorBoundary` على مستوى الجذر يمنع «الشاشة البيضاء» عند أي خطأ عرض غير متوقّع،
يعرض واجهة تعافٍ عربية («إعادة تحميل الصفحة»)، ويُبلِّغ Sentry (خامل ما لم يُضبط الـDSN).

## ما تبقّى (خارج نطاق هذا العمل)

- تفعيل CSP (حالياً Report-Only) — بند منفصل.
- فحص سلامة ما بعد الاستعادة (backup/restore drill) — بند منفصل.

## الجاهزية وهويّة الإصدار (Health + Release Identity)

- **`GET /api/health`** — فحص حقيقي: يتحقّق من إقلاع التطبيق واتصال قاعدة البيانات
  (التبعية الحرجة). متاحة ⇒ `200` `status:ok`؛ غير متاحة ⇒ `503` `status:error` كي يرصد
  Render/المراقبة العطل. يعرض `app/environment/version/commit/checks.database/timestamp`
  برسائل آمنة فقط (بلا SQL/أثر/مسار/اعتماد). لا يفحص تبعيات اختيارية (R2/Redis).
- **`GET /api/version`** — هويّة إصدار خفيفة بلا اتصال قاعدة بيانات: `app/version/commit/environment/laravel`.
- **بصمة الـcommit**: مصدرها الوحيد `RENDER_GIT_COMMIT` (يحقنه Render وقت النشر)، تُقرأ عبر
  `config('app.commit')`؛ إن غابت تُعاد `null` (بلا بديل مضلّل).
- **Render**: `healthCheckPath` يشير إلى `/api/health` (الفحص الحقيقي)؛ و`/up` يبقى فحص إقلاع سطحي.
