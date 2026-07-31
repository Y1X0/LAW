<?php

namespace Modules\Legal\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Legal\Models\CaseParty;
use Modules\Legal\Models\LegalCase;

/**
 * @extends Factory<CaseParty>
 */
class CasePartyFactory extends Factory
{
    protected $model = CaseParty::class;

    public function definition(): array
    {
        return [
            'case_id' => LegalCase::factory(),
            'name' => $this->faker->company(),
            'type' => $this->faker->randomElement(['plaintiff', 'defendant', 'witness', 'other']),
            'phone' => $this->faker->numerify('07########'),
        ];
    }
}
