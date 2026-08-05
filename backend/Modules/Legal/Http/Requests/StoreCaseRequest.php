<?php

namespace Modules\Legal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Legal\Models\LegalCase;

/**
 * تحقّق إنشاء قضية. الصلاحية تُفرَض عبر middleware المسار (cases.create).
 */
class StoreCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'internal_number' => ['required', 'string', 'max:50', 'unique:cases,internal_number'],
            'court_case_number' => ['nullable', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:200'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'responsible_lawyer_id' => ['nullable', 'integer', 'exists:employees,id'],
            'court_name' => ['nullable', 'string', 'max:150'],
            'case_type' => ['nullable', 'string', 'max:50'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(LegalCase::STATUSES)],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'opened_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
            // قيم الحقول المخصّصة (Phase 12): مفتاح الحقل ⇒ القيمة؛ يتحقّق منها CustomFieldValueService.
            'custom_fields' => ['sometimes', 'array'],
        ];
    }
}
