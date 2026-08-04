<?php

namespace Modules\Finance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Finance\Models\Invoice;
use Modules\Legal\Models\Client;

/**
 * الملخّص المالي للعميل (عرض 360°) (Finance / Phase 6 · PR-7).
 *
 * مجاميع محسوبة في قاعدة البيانات (لا في الواجهة) على الفواتير المُصدَرة فقط
 * (sent/partial/paid) — المسودّة والملغاة ليست مستحقات. محروس بـ invoices.view.
 */
class ClientFinanceSummaryController
{
    private const ISSUED = [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID];

    /** GET /api/finance/clients/{client}/summary */
    public function show(Client $client): JsonResponse
    {
        $base = Invoice::where('client_id', $client->id)->whereIn('status', self::ISSUED);

        return response()->json([
            'data' => [
                'client_id' => $client->id,
                'invoice_count' => (clone $base)->count(),
                'total_invoiced' => (string) number_format((float) (clone $base)->sum('total'), 2, '.', ''),
                'total_paid' => (string) number_format((float) (clone $base)->sum('paid_amount'), 2, '.', ''),
                'outstanding' => (string) number_format((float) (clone $base)->sum('balance'), 2, '.', ''),
            ],
            'meta' => null,
            'errors' => null,
        ]);
    }
}
