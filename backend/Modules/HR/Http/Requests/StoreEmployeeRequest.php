<?php

namespace Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\HR\Models\Employee;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحيات تُفحص عبر middleware permission:employees.create
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'employee_no' => ['required', 'string', 'max:30', 'unique:employees,employee_no'],
            'full_name_ar' => ['required', 'string', 'max:150'],
            'full_name_en' => ['nullable', 'string', 'max:150'],
            'national_id' => ['required', 'string', 'max:20', 'unique:employees,national_id'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'hire_date' => ['nullable', 'date'],
            'contract_type' => ['nullable', Rule::in(['permanent', 'temporary', 'part_time'])],
            'contract_start' => ['nullable', 'date'],
            'contract_end' => ['nullable', 'date', 'after_or_equal:contract_start'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(Employee::STATUSES)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
