<?php

namespace Modules\Finance\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\Invoice;
use Modules\Legal\Models\Client;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'invoice_no' => null,
            'client_id' => Client::factory(),
            'case_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => null,
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount' => 0,
            'total' => 0,
            'paid_amount' => 0,
            'balance' => 0,
            'status' => Invoice::STATUS_DRAFT,
        ];
    }
}
