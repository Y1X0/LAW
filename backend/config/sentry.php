<?php

use App\Support\SentryScrubber;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * إعداد Sentry (المراقبة والإبلاغ عن الأخطاء في الإنتاج).
 *
 * خامل افتراضياً: إن كان SENTRY_LARAVEL_DSN فارغاً لا يُرسَل شيء (كنمط MAIL_MAILER).
 * يُفعَّل بضبط الـDSN في لوحة Render. مضبوط لخصوصية نظام قانوني: بلا PII، بلا جسم
 * طلب، بلا بارامترات SQL — فقط ما يلزم للتشخيص. راجع docs/OBSERVABILITY.md.
 *
 * @see https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/
 */
return [
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    // إصدار النشر = بصمة الـcommit — يُلتقَط تلقائياً من RENDER_GIT_COMMIT على Render،
    // فيرتبط كل خطأ بالإصدار المنشور دون ضبط يدوي.
    'release' => env('SENTRY_RELEASE', env('RENDER_GIT_COMMIT')),

    // البيئة (production/staging) — تعود إلى APP_ENV إن تُركت فارغة.
    'environment' => env('SENTRY_ENVIRONMENT'),

    // نسبة أخذ العيّنات للأخطاء (1.0 = كل الأخطاء).
    'sample_rate' => env('SENTRY_SAMPLE_RATE') === null ? 1.0 : (float) env('SENTRY_SAMPLE_RATE'),

    // تتبّع الأداء (APM) — خامل افتراضياً (null)؛ ارفعه بحذر (مثل 0.1) عند الحاجة.
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE') === null ? null : (float) env('SENTRY_TRACES_SAMPLE_RATE'),

    // خصوصية صارمة: لا تُرسِل أي معلومات تعريف شخصية (IP/كوكيز/جسم الطلب/هوية المستخدم).
    'send_default_pii' => false,

    // لا تُرسِل جسم الطلب إطلاقاً (قد يحمل بيانات موكّلين/قضايا).
    'max_request_body_size' => 'none',

    // تنقية إضافية قبل الإرسال (حزام أمان فوق send_default_pii=false).
    'before_send' => [SentryScrubber::class, 'scrub'],

    // لا تُبلِغ عن استثناءات المصادقة/التحقّق/الصلاحيات المتوقّعة (ضجيج لا أعطال).
    'ignore_exceptions' => [
        AuthenticationException::class,
        AuthorizationException::class,
        ValidationException::class,
        ModelNotFoundException::class,
        NotFoundHttpException::class,
        AccessDeniedHttpException::class,
    ],

    // لا تُتبّع نقاط الفحص الصحّي.
    'ignore_transactions' => ['/up', '/api/health', '/api/version'],

    'breadcrumbs' => [
        'logs' => true,
        'cache' => true,
        // لا نلتقط بارامترات SQL في breadcrumbs (قد تحمل قيم بيانات حسّاسة).
        'sql_queries' => true,
        'sql_bindings' => false,
    ],

    'tracing' => [
        // لا نُضيف الاستعلامات كـ spans تفصيلية افتراضياً.
        'sql_bindings' => false,
    ],
];
