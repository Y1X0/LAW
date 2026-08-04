<?php

namespace Modules\Finance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Services\ReportService;

/**
 * التقارير المالية (Finance / Phase 6 · PR-10) — قراءة فقط، محروسة بـ finance.reports.
 * المجاميع من ReportService (SQL) — لا حساب هنا ولا في الواجهة.
 */
class FinanceReportController
{
    public function __construct(private readonly ReportService $reports) {}

    /** GET /api/finance/reports/trial-balance?from=&to= */
    public function trialBalance(Request $request): JsonResponse
    {
        return $this->ok($this->reports->trialBalance($request->query('from'), $request->query('to')));
    }

    /** GET /api/finance/reports/receivables */
    public function receivables(): JsonResponse
    {
        return $this->ok($this->reports->receivables());
    }

    /** GET /api/finance/reports/income-expense?from=&to= */
    public function incomeExpense(Request $request): JsonResponse
    {
        return $this->ok($this->reports->incomeExpense($request->query('from'), $request->query('to')));
    }

    private function ok($data): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null]);
    }
}
