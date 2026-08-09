<?php

namespace Modules\Finance\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * يُرمى عند محاولة تعديل أو حذف قيد مُرحَّل أو أحد سطوره (Finance / Phase 6 · PR-3).
 * القيد المُرحَّل نهائي — التصحيح يكون بقيد عكس فقط.
 *
 * يعرض نفسه على مخطّط الخطأ الموحّد {data,meta,errors} برمز واضح ورسالة عربية
 * بدل خطأ 500 عامّ — 409 لأنّه تعارض مع حالة السجلّ (مُرحَّل/نهائي).
 */
class ImmutableJournalEntryException extends RuntimeException
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
                'code' => 'IMMUTABLE_JOURNAL_ENTRY',
                'message' => $this->getMessage() ?: 'القيد مُرحَّل ولا يمكن تعديله.',
            ],
        ], 409);
    }
}
