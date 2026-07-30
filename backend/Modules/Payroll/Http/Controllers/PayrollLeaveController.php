<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\PayrollLeaveSummary;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Services\PayrollLeaveService;

/**
 * تكامل الإجازات مع الرواتب (Issue #35). قراءة: payroll.view · لقطة: payroll.create.
 */
class PayrollLeaveController
{
    public function __construct(private readonly PayrollLeaveService $service) {}

    /** GET /api/employees/{employee}/leave-summary?year=&month= — معاينة حيّة (بلا حفظ). */
    public function preview(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        return $this->ok($this->service->summarize($employee, $data['year'], $data['month']));
    }

    /** GET /api/payroll-runs/{payroll_run}/leave-summaries — لقطات المسير المحفوظة. */
    public function index(PayrollRun $payroll_run): JsonResponse
    {
        $summaries = PayrollLeaveSummary::where('payroll_run_id', $payroll_run->id)
            ->with('employee:id,full_name_ar,employee_no')
            ->get();

        return $this->ok($summaries);
    }

    /** POST /api/payroll-runs/{payroll_run}/leave-snapshot — بناء لقطات الإجازات للمسير. */
    public function snapshot(Request $request, PayrollRun $payroll_run): JsonResponse
    {
        $count = $this->service->snapshotRun($payroll_run, $request);

        return $this->ok(['payroll_run_id' => $payroll_run->id, 'employees' => $count]);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
