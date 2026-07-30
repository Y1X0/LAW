<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\EmployeeSalaryComponent;
use Modules\Payroll\Models\SalaryComponent;
use Modules\Payroll\Services\SalaryComponentService;

/**
 * إسناد مكوّنات الراتب للموظفين (Issue #33). قراءة: payroll.view · كتابة: payroll.create.
 */
class EmployeeSalaryComponentController
{
    public function __construct(private readonly SalaryComponentService $service) {}

    /** GET /api/employees/{employee}/salary-components — إسنادات الموظف (اختياري ?active=1). */
    public function index(Request $request, Employee $employee): JsonResponse
    {
        $query = EmployeeSalaryComponent::where('employee_id', $employee->id)
            ->with('component:id,name,code,type,value_type');
        if ($request->boolean('active')) {
            $query->where('is_active', true);
        }

        return $this->ok($query->orderByDesc('effective_from')->get());
    }

    /** POST /api/employees/{employee}/salary-components — إسناد مكوّن نشط. */
    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'salary_component_id' => ['required', 'integer', 'exists:salary_components,id'],
            'value' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
        ]);
        $component = SalaryComponent::where('id', $data['salary_component_id'])->where('is_active', true)->firstOrFail();

        return $this->ok($this->service->assign($employee, $component, $data, $request), 201);
    }

    /** DELETE /api/employee-salary-components/{assignment} — إيقاف الإسناد (أرشفة). */
    public function destroy(Request $request, EmployeeSalaryComponent $employee_salary_component): JsonResponse
    {
        return $this->ok($this->service->deactivate($employee_salary_component, $request));
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
