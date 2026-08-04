<?php

namespace Modules\Finance\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\FinancialAccount;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\Payment;
use Modules\Legal\Models\Client;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'receipt_no' => null,
            'invoice_id' => Invoice::factory(),
            'client_id' => Client::factory(),
            'amount' => 100,
            'method' => 'cash',
            'account_id' => FinancialAccount::factory(),
            'payment_date' => now()->toDateString(),
        ];
    }
}
