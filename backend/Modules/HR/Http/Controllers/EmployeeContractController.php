<?php

namespace Modules\HR\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Core\Concerns\RecordsAudit;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeContract;

/**
 * عقود التوظيف لموظف (Issue #14). قراءة: employees.view · كتابة: employees.update.
 */
class EmployeeContractController
{
    use RecordsAudit;

    public function index(Request $request, Employee $employee): JsonResponse
    {
        // إخفاء الراتب الأساسي عمّن لا يملك employees.salary.view (اتساقاً مع قناع
        // الموظف نفسه في EmployeeController::present — يمنع تسريب الراتب عبر العقود).
        $canViewSalary = $request->user()?->hasPermission('employees.salary.view') ?? false;

        $contracts = $employee->contracts()->orderByDesc('start_date')->get()
            ->map(function (EmployeeContract $contract) use ($canViewSalary) {
                $row = $contract->toArray();
                if (! $canViewSalary) {
                    unset($row['basic_salary']);
                }

                return $row;
            });

        return $this->ok($contracts);
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'contract_type' => ['required', Rule::in(['permanent', 'temporary', 'part_time'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(EmployeeContract::STATUSES)],
            'file_path' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        // من لا يملك employees.salary.view لا يضبط الراتب على العقد (اتساقاً مع SEC-9).
        if (! $this->canViewSalary($request)) {
            unset($data['basic_salary']);
        }

        $data['employee_id'] = $employee->id;
        $data['created_by'] = $request->user()?->id;
        $contract = EmployeeContract::create($data);

        $this->recordAudit($request, 'employee_contract_added', Employee::class, $employee->id, [
            'contract_id' => $contract->id, 'type' => $contract->contract_type,
        ]);

        return $this->ok($this->maskSalary($contract, $request), 201);
    }

    public function update(Request $request, Employee $employee, EmployeeContract $contract): JsonResponse
    {
        abort_unless($contract->employee_id === $employee->id, 404);

        $data = $request->validate([
            'status' => ['sometimes', Rule::in(EmployeeContract::STATUSES)],
            'end_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $contract->update($data);
        $this->recordAudit($request, 'employee_contract_updated', Employee::class, $employee->id, [
            'contract_id' => $contract->id,
        ]);

        return $this->ok($this->maskSalary($contract, $request));
    }

    private function canViewSalary(Request $request): bool
    {
        return $request->user()?->hasPermission('employees.salary.view') ?? false;
    }

    /**
     * يُخفي basic_salary من تمثيل العقد لمن لا يملك employees.salary.view — نفس قناع
     * القائمة (index) و EmployeeController::present، فلا يُسرَّب الراتب عبر ردّ store/update.
     */
    private function maskSalary(EmployeeContract $contract, Request $request): array
    {
        $row = $contract->toArray();
        if (! $this->canViewSalary($request)) {
            unset($row['basic_salary']);
        }

        return $row;
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
