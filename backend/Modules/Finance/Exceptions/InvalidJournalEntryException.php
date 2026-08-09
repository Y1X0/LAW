<?php

namespace Modules\Finance\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * يُرمى عندما يخالف قيدٌ قواعد الصحّة قبل الترحيل (Finance / Phase 6 · PR-3):
 * اختلال التوازن، أقل من سطرين، قيم سالبة/صفرية، أو حساب غير صالح/غير نشط.
 *
 * يعرض نفسه على مخطّط الخطأ الموحّد {data,meta,errors} برمز واضح ورسالة عربية
 * دقيقة (من موضع الرمي) بدل خطأ 500 عامّ — 422 لأنّه بيانات غير صالحة.
 */
class InvalidJournalEntryException extends RuntimeException
{
    public function render(Request $request): ?JsonResponse
    {
        if (! ($request->is('api/*') || $request->expectsJson())) {
            return null;
        }

        return response()->json([
            'data' => null,
            'meta' => null,
            'errors' => [
                'code' => 'INVALID_JOURNAL_ENTRY',
                'message' => $this->getMessage(),
            ],
        ], 422);
    }
}
