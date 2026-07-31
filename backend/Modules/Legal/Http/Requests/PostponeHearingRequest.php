<?php

namespace Modules\Legal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * تحقّق تأجيل جلسة (hearings.manage): الموعد الجديد + سبب التأجيل.
 */
class PostponeHearingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date'],
            'postponed_reason' => ['required', 'string', 'max:255'],
        ];
    }
}
