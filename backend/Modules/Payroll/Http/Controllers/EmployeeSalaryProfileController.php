<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\EmployeeSalaryProfile;
use Modules\Payroll\Services\PayrollService;

/**
 * ملفات رواتب الموظفين (Issue #32). قراءة: payroll.view · ضبط: payroll.create.
 */
class EmployeeSalaryProfileController
{
    public function __construct(private readonly PayrollService $service) {}

    /** GET /api/employees/{employee}/salary-profiles — تاريخ ملفات راتب الموظف. */
    public function index(Employee $employee): JsonResponse
    {
        $profiles = EmployeeSalaryProfile::where('employee_id', $employee->id)
            ->orderByDesc('effective_from')
            ->get();

        return $this->ok($profiles);
    }

    /** POST /api/employees/{employee}/salary-profiles — ضبط ملف راتب نشط جديد. */
    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_method' => ['nullable', Rule::in(EmployeeSalaryProfile::PAYMENT_METHODS)],
            'effective_from' => ['nullable', 'date'],
        ]);

        return $this->ok($this->service->setSalaryProfile($employee, $data, $request), 201);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
