<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Payroll\Models\PayrollItem;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Services\PayslipService;

/**
 * كشوف الرواتب (Issue #37). عرض/تصدير: payroll.view. يعرض النتائج المحفوظة فقط.
 */
class PayslipController
{
    use RecordsAudit;

    public function __construct(private readonly PayslipService $service) {}

    /** GET /api/payroll-runs/{payroll_run}/payslips — كشوف موظفي المسير. */
    public function index(PayrollRun $payroll_run): JsonResponse
    {
        $items = PayrollItem::where('payroll_run_id', $payroll_run->id)
            ->with('employee:id,full_name_ar,employee_no')
            ->get()
            ->map(fn (PayrollItem $i) => [
                'payroll_item_id' => $i->id,
                'employee' => ['name' => $i->employee?->full_name_ar, 'employee_number' => $i->employee?->employee_no],
                'gross' => (float) $i->gross_amount,
                'deductions_total' => (float) $i->deductions_total,
                'net' => (float) $i->net_amount,
                'currency' => $i->currency,
            ]);

        return response()->json(['data' => $items, 'meta' => null, 'errors' => null]);
    }

    /** GET /api/payroll-items/{payroll_item}/payslip — كشف راتب (JSON). */
    public function show(PayrollItem $payroll_item): JsonResponse
    {
        return response()->json(['data' => $this->service->toArray($payroll_item), 'meta' => null, 'errors' => null]);
    }

    /** GET /api/payroll-items/{payroll_item}/payslip/html — كشف راتب مطبوع (HTML). */
    public function html(Request $request, PayrollItem $payroll_item): Response
    {
        $this->recordAudit($request, 'payslip_exported', PayrollItem::class, $payroll_item->id, [
            'payroll_run_id' => $payroll_item->payroll_run_id, 'employee_id' => $payroll_item->employee_id,
        ]);

        return response($this->service->toHtml($payroll_item), 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
