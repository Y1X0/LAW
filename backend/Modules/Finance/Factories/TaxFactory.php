<?php

namespace Modules\Finance\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\Tax;

/**
 * @extends Factory<Tax>
 */
class TaxFactory extends Factory
{
    protected $model = Tax::class;

    public function definition(): array
    {
        return [
            'name' => Tax::VAT,
            'rate' => 15.00,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
