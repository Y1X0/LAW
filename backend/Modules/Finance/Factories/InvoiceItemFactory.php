<?php

namespace Modules\Finance\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\InvoiceItem;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $quantity = 1;
        $unitPrice = $this->faker->numberBetween(100, 1000);

        return [
            'invoice_id' => Invoice::factory(),
            'description' => $this->faker->sentence(3),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_rate' => 15,
            'line_total' => $quantity * $unitPrice,
        ];
    }
}
