<?php

namespace Modules\Legal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * تحقّق إضافة حدث للخط الزمني (cases.update). Append-Only — لا تعديل.
 */
class StoreTimelineEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'event_type' => ['nullable', 'string', 'max:60'],
            'event_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
