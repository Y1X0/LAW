<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * HealthController — فحص جاهزية النظام (وحدة Core).
 * يُستخدم للمراقبة (Monitoring) وللتحقق من إقلاع التطبيق واتصال قاعدة البيانات.
 */
class HealthController
{
    public function __invoke(): JsonResponse
    {
        $database = 'ok';

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $database = 'unavailable';
        }

        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'environment' => config('app.env'),
            'database' => $database,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
