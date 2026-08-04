<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Finance\Models\Payment;

/**
 * تحقّق تسجيل سند قبض (Finance / Phase 6 · PR-6). الصلاحية عبر middleware المسار
 * (payments.create). قواعد الحالة/الرصيد والـIdempotency تُفرَض في PaymentService.
 */
class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'account_id' => ['required', 'integer', Rule::exists('financial_accounts', 'id')],
            'reference' => ['nullable', 'string', 'max:80'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
