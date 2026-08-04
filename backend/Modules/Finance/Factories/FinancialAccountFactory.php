<?php

namespace Modules\Finance\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\FinancialAccount;

/**
 * @extends Factory<FinancialAccount>
 */
class FinancialAccountFactory extends Factory
{
    protected $model = FinancialAccount::class;

    public function definition(): array
    {
        return [
            'name' => 'صندوق '.$this->faker->unique()->word(),
            'type' => 'cash',
            'account_number' => null,
            'opening_balance' => 0,
            'current_balance' => 0,
            'currency' => 'SAR',
            'is_active' => true,
        ];
    }

    public function bank(): static
    {
        return $this->state(fn () => [
            'type' => 'bank',
            'name' => 'بنك '.$this->faker->unique()->word(),
            'account_number' => (string) $this->faker->numerify('SA############'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
