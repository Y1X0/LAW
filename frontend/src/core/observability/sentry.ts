import * as Sentry from '@sentry/react'

/**
 * تهيئة مراقبة الأخطاء (Sentry) للواجهة.
 *
 * خاملة افتراضياً: بلا `VITE_SENTRY_DSN` لا يُرسَل شيء (نمط MAIL_MAILER في الخلفية).
 * مضبوطة لخصوصية نظام قانوني: بلا PII، ولا نُرسِل بيانات الطلب/المستخدم — فقط الأثر
 * التقني اللازم للتشخيص. تُفعَّل بضبط الـDSN وقت البناء في لوحة Render.
 */
export function initSentry(): void {
  const dsn = import.meta.env.VITE_SENTRY_DSN as string | undefined
  if (!dsn) return // خامل — لا مراقبة بلا DSN

  Sentry.init({
    dsn,
    release: (import.meta.env.VITE_SENTRY_RELEASE as string | undefined) || undefined,
    environment: (import.meta.env.VITE_SENTRY_ENVIRONMENT as string | undefined) || import.meta.env.MODE,
    // خصوصية صارمة: لا معلومات تعريف شخصية.
    sendDefaultPii: false,
    // معدّل تتبّع الأداء منخفض جداً (خامل ما لم يُضبط).
    tracesSampleRate: Number(import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE ?? 0),
    // حزام أمان: نُزيل أي هوية مستخدم أو بيانات طلب قبل الإرسال.
    beforeSend(event) {
      delete event.user
      if (event.request) {
        delete event.request.data
        delete event.request.cookies
        delete event.request.query_string
      }
      return event
    },
  })
}

/** التقاط استثناء يدوياً (خامل ما لم تُهيّأ Sentry). يُستخدم في حدود الأخطاء. */
export function captureError(error: unknown): void {
  Sentry.captureException(error)
}
