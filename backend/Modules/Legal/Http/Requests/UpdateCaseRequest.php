<?php

namespace Modules\Legal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Legal\Models\LegalCase;

/**
 * تحقّق تعديل قضية.
 */
class UpdateCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $caseId = $this->route('case')?->id;

        return [
            'internal_number' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('cases', 'internal_number')->ignore($caseId)],
            'court_case_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'client_id' => ['sometimes', 'required', 'integer', 'exists:clients,id'],
            'responsible_lawyer_id' => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],
            'court_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'case_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(LegalCase::STATUSES)],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'opened_date' => ['sometimes', 'nullable', 'date'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            // قيم الحقول المخصّصة (Phase 12): مفتاح الحقل ⇒ القيمة؛ يتحقّق منها CustomFieldValueService.
            'custom_fields' => ['sometimes', 'array'],
        ];
    }
}
