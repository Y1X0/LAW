<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Services\PayrollService;

/**
 * مسيرات الرواتب (Issue #32). قراءة: payroll.view · إنشاء: payroll.create.
 */
class PayrollRunController
{
    public function __construct(private readonly PayrollService $service) {}

    /** GET /api/payroll-periods/{payroll_period}/runs — مسيرات فترة. */
    public function index(PayrollPeriod $payroll_period): JsonResponse
    {
        return $this->ok($payroll_period->runs()->orderByDesc('created_at')->get());
    }

    /** POST /api/payroll-periods/{payroll_period}/runs — إنشاء مسير للفترة. */
    public function store(Request $request, PayrollPeriod $payroll_period): JsonResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        return $this->ok($this->service->createRun($payroll_period, $data, $request), 201);
    }

    public function show(PayrollRun $payroll_run): JsonResponse
    {
        return $this->ok($payroll_run->load('period:id,year,month,status'));
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
