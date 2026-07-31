<?php

namespace Modules\Legal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * تحقّق تسجيل الإنجاز اليومي (worklog.submit_own) — لليوم الحالي فقط.
 */
class StoreWorklogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'done_today' => ['required', 'string', 'max:5000'],
            'plan_tomorrow' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
