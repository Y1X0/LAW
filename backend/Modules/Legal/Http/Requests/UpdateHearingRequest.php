<?php

namespace Modules\Legal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Legal\Models\Hearing;

/**
 * تحقّق تعديل جلسة (لا يُسمح بتعديل جلسة held — يُفرَض في الخدمة).
 */
class UpdateHearingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['sometimes', 'date'],
            'type' => ['sometimes', 'nullable', 'string', 'max:60'],
            'status' => ['sometimes', Rule::in(Hearing::STATUSES)],
            'location' => ['sometimes', 'nullable', 'string', 'max:150'],
            'outcome' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
