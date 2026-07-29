<?php

namespace Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\HR\Models\Employee;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission:employees.update
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
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in(Employee::STATUSES)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
