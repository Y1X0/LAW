<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\PayrollAttendanceSummary;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Services\PayrollAttendanceService;

/**
 * تكامل الحضور مع الرواتب (Issue #34). قراءة: payroll.view · لقطة: payroll.create.
 */
class PayrollAttendanceController
{
    public function __construct(private readonly PayrollAttendanceService $service) {}

    /** GET /api/employees/{employee}/attendance-summary?year=&month= — معاينة حيّة (بلا حفظ). */
    public function preview(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $summary = $this->service->summarize($employee, $data['year'], $data['month']);
        $summary['overtime_hours'] = round($summary['overtime_minutes'] / 60, 2);

        return $this->ok($summary);
    }

    /** GET /api/payroll-runs/{payroll_run}/attendance-summaries — لقطات المسير المحفوظة. */
    public function index(PayrollRun $payroll_run): JsonResponse
    {
        $summaries = PayrollAttendanceSummary::where('payroll_run_id', $payroll_run->id)
            ->with('employee:id,full_name_ar,employee_no')
            ->get();

        return $this->ok($summaries);
    }

    /** POST /api/payroll-runs/{payroll_run}/attendance-snapshot — بناء لقطات الحضور للمسير. */
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
