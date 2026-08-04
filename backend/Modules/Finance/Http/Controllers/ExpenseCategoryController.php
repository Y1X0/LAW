<?php

namespace Modules\Finance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Finance\Models\ExpenseCategory;

/**
 * تصنيفات المصروفات للقراءة (Finance / Phase 6 · PR-9).
 * تغذّي قائمة اختيار التصنيف في نموذج سند الصرف. محروسة بـ expenses.create.
 */
class ExpenseCategoryController
{
    /** GET /api/finance/expense-categories — التصنيفات النشطة. */
    public function index(): JsonResponse
    {
        $categories = ExpenseCategory::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $categories, 'meta' => null, 'errors' => null]);
    }
}
