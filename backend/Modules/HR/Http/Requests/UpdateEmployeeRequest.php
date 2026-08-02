<?php

namespace Modules\HR\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Models\Department;
use Modules\HR\Models\Employee;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission:employees.update
    }

    /** قاعدة عمل: القسم (بعد التعديل) يجب أن يتبع الفرع (بعد التعديل). */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $employee = $this->route('employee');

            // القسم يتبع الفرع — يُفحص فقط إن مُسّ أحدهما في الطلب.
            if ($this->has('branch_id') || $this->has('department_id')) {
                $branchId = $this->input('branch_id', $employee->branch_id);
                $departmentId = $this->input('department_id', $employee->department_id);
                if ($branchId && $departmentId
                    && ! Department::where('id', $departmentId)->where('branch_id', $branchId)->exists()) {
                    $v->errors()->add('department_id', 'القسم المختار لا يتبع الفرع المحدّد.');
                }
            }

            // منع الحلقات الإدارية: المدير الجديد يجب ألّا يكون هذا الموظف نفسه أو أحد
            // مرؤوسيه (مباشرين أو غير مباشرين) — وإلّا نشأت حلقة A↔B.
            if ($this->filled('manager_id') && $this->createsManagerCycle($employee->id, (int) $this->input('manager_id'))) {
                $v->errors()->add('manager_id', 'اختيار هذا المدير ينشئ حلقة إدارية غير صالحة.');
            }
        });
    }

    /** هل يقع $managerId ضمن السلسلة الإدارية الصاعدة من نفسه حتى تصل إلى $employeeId؟ */
    private function createsManagerCycle(int $employeeId, int $managerId): bool
    {
        $current = $managerId;
        $guard = 0;
        while ($guard++ < 100) {
            if ($current === $employeeId) {
                return true;
            }
            $next = Employee::where('id', $current)->value('manager_id');
            if ($next === null) {
                break;
            }
            $current = (int) $next;
        }

        return false;
    }

    public function rules(): array
    {
        $id = $this->route('employee')->id;

        return [
            'branch_id' => ['sometimes', 'integer', 'exists:branches,id'],
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'employee_no' => ['sometimes', 'string', 'max:30', Rule::unique('employees', 'employee_no')->ignore($id)],
            'full_name_ar' => ['sometimes', 'string', 'max:150'],
            'full_name_en' => ['nullable', 'string', 'max:150'],
            'national_id' => ['sometimes', 'string', 'max:20', Rule::unique('employees', 'national_id')->ignore($id)],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id', 'not_in:'.$id],
            'hire_date' => ['nullable', 'date'],
            'contract_type' => ['nullable', Rule::in(['permanent', 'temporary', 'part_time'])],
            'contract_start' => ['nullable', 'date'],
            'contract_end' => ['nullable', 'date', 'after_or_equal:contract_start'],
            'termination_date' => ['nullable', 'date'],
            'termination_reason' => ['nullable', 'string', 'max:255'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in(Employee::STATUSES)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
