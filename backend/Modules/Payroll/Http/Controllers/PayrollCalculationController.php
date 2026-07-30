<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payroll\Models\PayrollItem;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Services\PayrollCalculationService;

/**
 * محرّك حساب الرواتب (Issue #36). قراءة النتائج: payroll.view · التشغيل: payroll.create.
 */
class PayrollCalculationController
{
    public function __construct(private readonly PayrollCalculationService $service) {}

    /** POST /api/payroll-runs/{payroll_run}/calculate — تشغيل الحساب للمسير. */
    public function calculate(Request $request, PayrollRun $payroll_run): JsonResponse
    {
        $count = $this->service->calculateRun($payroll_run, $request);

        return $this->ok(['payroll_run_id' => $payroll_run->id, 'employees' => $count]);
    }

    /** GET /api/payroll-runs/{payroll_run}/items — نتائج الحساب للمسير. */
    public function index(PayrollRun $payroll_run): JsonResponse
    {
        $items = PayrollItem::where('payroll_run_id', $payroll_run->id)
            ->with('employee:id,full_name_ar,employee_no')
            ->get();

        return $this->ok($items);
    }

    /** GET /api/payroll-items/{payroll_item} — تفصيل بند راتب موظف. */
    public function show(PayrollItem $payroll_item): JsonResponse
    {
        return $this->ok($payroll_item->load('employee:id,full_name_ar,employee_no'));
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
