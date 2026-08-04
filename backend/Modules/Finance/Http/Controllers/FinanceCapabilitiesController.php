<?php

namespace Modules\Finance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * قدرات المستخدم المالية (Finance / Phase 6 · PR-5).
 *
 * الواجهة لا تملك مصدراً لصلاحيات المستخدم (/auth/me لا يُرجعها)، فتقرأ منها هذه النقطة
 * لتقرّر إظهار الأزرار (إنشاء/اعتماد) بدقّة. الخادم يبقى الحكم النهائي على كل عملية —
 * هذه للعرض فقط. محروسة بالمصادقة فقط (تُرجع قيماً حسب صلاحيات المستخدم).
 */
class FinanceCapabilitiesController
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'can_view' => (bool) $user?->hasPermission('invoices.view'),
                'can_create' => (bool) $user?->hasPermission('invoices.create'),
                'can_approve' => (bool) $user?->hasPermission('invoices.approve'),
                'can_record_payment' => (bool) $user?->hasPermission('payments.create'),
                'can_record_expense' => (bool) $user?->hasPermission('expenses.create'),
                'can_view_reports' => (bool) $user?->hasPermission('finance.reports'),
            ],
            'meta' => null,
            'errors' => null,
        ]);
    }
}
