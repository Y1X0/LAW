<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Finance\Models\Expense;

/**
 * تحقّق تسجيل سند صرف (Finance / Phase 6 · PR-8). الصلاحية عبر middleware المسار
 * (expenses.create). عزل القضية (عند وجود case_id) والقيد يُفرَضان في المتحكّم/الخدمة.
 */
class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')],
            'case_id' => ['nullable', 'integer', Rule::exists('cases', 'id')],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(Expense::METHODS)],
            'account_id' => ['required', 'integer', Rule::exists('financial_accounts', 'id')],
            'beneficiary' => ['nullable', 'string', 'max:150'],
            'expense_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
