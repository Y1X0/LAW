<?php

namespace Modules\Finance\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\Expense;
use Modules\Finance\Models\ExpenseCategory;
use Modules\Finance\Models\FinancialAccount;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'voucher_no' => null,
            'category_id' => ExpenseCategory::factory(),
            'case_id' => null,
            'amount' => 500,
            'method' => 'cash',
            'account_id' => FinancialAccount::factory(),
            'expense_date' => now()->toDateString(),
        ];
    }
}
