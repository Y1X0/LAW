<?php

namespace Modules\Legal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Legal\Models\CaseAssignment;

/**
 * تحقّق إسناد محامٍ لقضية (cases.assign).
 */
class AssignLawyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'role' => ['required', Rule::in(CaseAssignment::ROLES)],
        ];
    }
}
