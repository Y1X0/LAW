<?php

namespace Modules\Finance\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\JournalEntry;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    public function definition(): array
    {
        return [
            'entry_no' => null,
            'entry_date' => now()->toDateString(),
            'description' => $this->faker->sentence(3),
            'reference_type' => null,
            'reference_id' => null,
            'posted' => false,
        ];
    }

    public function posted(): static
    {
        return $this->state(fn () => ['posted' => true, 'posted_at' => now()]);
    }
}
