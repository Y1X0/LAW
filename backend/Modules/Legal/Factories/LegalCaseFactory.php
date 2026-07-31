<?php

namespace Modules\Legal\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Legal\Models\Client;
use Modules\Legal\Models\LegalCase;

/**
 * @extends Factory<LegalCase>
 */
class LegalCaseFactory extends Factory
{
    protected $model = LegalCase::class;

    public function definition(): array
    {
        return [
            'internal_number' => 'CASE-'.$this->faker->unique()->numerify('2026-###'),
            'court_case_number' => (string) $this->faker->numerify('20##/###'),
            'title' => $this->faker->sentence(4),
            'client_id' => Client::factory(),
            'responsible_lawyer_id' => null,
            'court_name' => 'محكمة عمّان الابتدائية',
            'case_type' => $this->faker->randomElement(['مدني', 'جزائي', 'تجاري', 'شرعي', 'عمالي']),
            'value' => $this->faker->randomFloat(2, 1000, 500000),
            'status' => 'open',
            'progress' => 0,
            'opened_date' => now()->toDateString(),
            'description' => $this->faker->paragraph(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'closed', 'progress' => 100]);
    }
}
