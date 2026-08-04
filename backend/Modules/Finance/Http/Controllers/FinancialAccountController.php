<?php

namespace Modules\Finance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Finance\Models\FinancialAccount;

/**
 * الحسابات النقدية (صندوق/بنك) للقراءة (Finance / Phase 6 · PR-7).
 * تغذّي قائمة اختيار الحساب المستلِم في نموذج سند القبض. محروسة بـ payments.create.
 */
class FinancialAccountController
{
    /** GET /api/finance/accounts — الحسابات النشطة. */
    public function index(): JsonResponse
    {
        $accounts = FinancialAccount::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'currency']);

        return response()->json(['data' => $accounts, 'meta' => null, 'errors' => null]);
    }
}
