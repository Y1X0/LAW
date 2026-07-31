<?php

namespace Modules\Legal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Legal\Models\CaseTask;

/**
 * تحقّق تعديل مهمة (tasks.create). الإكمال/الإسناد عبر نقاط مستقلّة.
 */
class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'priority' => ['sometimes', Rule::in(CaseTask::PRIORITIES)],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'case_id' => ['sometimes', 'nullable', 'integer', 'exists:cases,id'],
        ];
    }
}
