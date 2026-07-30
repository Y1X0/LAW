<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Services\PayrollApprovalService;

/**
 * اعتماد/قفل مسير الرواتب (Issue #37). اعتماد: payroll.approve · قفل: payroll.pay.
 */
class PayrollApprovalController
{
    public function __construct(private readonly PayrollApprovalService $service) {}

    /** POST /api/payroll-runs/{payroll_run}/approve */
    public function approve(Request $request, PayrollRun $payroll_run): JsonResponse
    {
        return $this->ok($this->service->approve($payroll_run, $request));
    }

    /** POST /api/payroll-runs/{payroll_run}/lock */
    public function lock(Request $request, PayrollRun $payroll_run): JsonResponse
    {
        return $this->ok($this->service->lock($payroll_run, $request));
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
