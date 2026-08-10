<?php

namespace App\Support;

use Sentry\Event;
use Sentry\EventHint;

/**
 * تنقية أحداث Sentry قبل الإرسال — دفاع عن خصوصية بيانات نظام قانوني.
 *
 * حتى مع send_default_pii=false، نُزيل صراحةً أي حمولة طلب/استعلام/كوكيز قد تحمل
 * بيانات عملاء أو قضايا أو أسراراً، ونحتفظ فقط بما يلزم للتشخيص (النوع/الأثر/المسار).
 * الرسائل التقنية تذهب إلى Sentry؛ بيانات الموكّلين لا تُغادر الخادم أبداً.
 */
class SentryScrubber
{
    /** مفاتيح حسّاسة تُحجب أينما ظهرت في الأحداث. */
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'token', 'access_token',
        'refresh_token', 'secret', 'authorization', 'cookie', 'set-cookie',
        'national_id', 'iban', 'salary', 'basic_salary',
    ];

    /** يُستدعى كـ before_send: يُعيد الحدث بعد إزالة أي بيانات حسّاسة. */
    public static function scrub(Event $event, ?EventHint $hint = null): ?Event
    {
        $request = $event->getRequest();
        if ($request !== []) {
            // لا نُرسل جسم الطلب أو الاستعلام أو الكوكيز إطلاقاً (قد تحمل بيانات موكّلين).
            unset($request['data'], $request['query_string'], $request['cookies']);
            if (isset($request['headers']) && is_array($request['headers'])) {
                $request['headers'] = self::redactHeaders($request['headers']);
            }
            $event->setRequest($request);
        }

        // لا نربط هوية مستخدم (بريد/IP) بالأخطاء.
        $event->setUser(null);

        // تنقية سياقات إضافية إن وُجدت.
        $extra = $event->getExtra();
        if ($extra !== []) {
            $event->setExtra(self::redactArray($extra));
        }

        return $event;
    }

    /** @param array<string,mixed> $headers */
    private static function redactHeaders(array $headers): array
    {
        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), self::SENSITIVE_KEYS, true)) {
                $headers[$name] = '[redacted]';
            }
        }

        return $headers;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private static function redactArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = self::redactArray($value);
            }
        }

        return $data;
    }
}
