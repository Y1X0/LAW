<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * HealthController — فحص جاهزية النظام + هويّة الإصدار (وحدة Core).
 *
 * فحص حقيقي: يتأكّد من إقلاع التطبيق واتصال قاعدة البيانات (التبعية الحرجة —
 * الجلسات/الكاش/الطابور كلّها على Postgres). قاعدة البيانات متاحة ⇒ 200 status=ok؛
 * غير متاحة ⇒ 503 status=error كي يرصد Render/المراقبة العطل. لا يفحص تبعيات
 * اختيارية (R2/Redis) كي لا يُسقط خدمةً تعمل. يعرض هويّة الإصدار (commit/version).
 *
 * أمان: لا يكشف SQL أو أثراً أو مساراً أو بيانات اعتماد أو أسماء استثناءات — التفاصيل
 * التقنية تُسجَّل داخلياً فقط.
 */
class HealthController
{
    public function __invoke(): JsonResponse
    {
        $databaseOk = $this->databaseReachable();

        return response()->json([
            'status' => $databaseOk ? 'ok' : 'error',
            'app' => config('app.name'),
            'environment' => config('app.env'),
            'version' => config('app.version'),
            'commit' => config('app.commit'), // null إن لم يُضبط RENDER_GIT_COMMIT (بلا بديل مضلّل)
            'checks' => [
                'database' => $databaseOk ? 'ok' : 'unavailable',
            ],
            'timestamp' => now()->toIso8601String(),
        ], $databaseOk ? 200 : 503);
    }

    /** يتحقّق من اتصال قاعدة البيانات دون تسريب أي تفاصيل للمستجيب. */
    private function databaseReachable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable $e) {
            // التفاصيل تُسجَّل داخلياً (سجلّات/Sentry) ولا تُعرَض للمستخدم.
            report($e);

            return false;
        }
    }
}
