<?php

namespace Modules\Legal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Legal\Models\CaseTask;

/**
 * تحقّق إنشاء مهمة (tasks.create).
 */
class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['sometimes', Rule::in(CaseTask::PRIORITIES)],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['required', 'integer', 'exists:employees,id'],
            'case_id' => ['nullable', 'integer', 'exists:cases,id'],
        ];
    }
}
