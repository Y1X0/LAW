<?php

namespace Modules\Legal\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Legal\Models\CaseTimelineEvent;
use Modules\Legal\Models\LegalCase;

/**
 * @extends Factory<CaseTimelineEvent>
 */
class CaseTimelineEventFactory extends Factory
{
    protected $model = CaseTimelineEvent::class;

    public function definition(): array
    {
        return [
            'case_id' => LegalCase::factory(),
            'title' => $this->faker->randomElement(['فتح القضية', 'تقديم مذكرة دفاع', 'تحديد أول جلسة', 'صدور حكم']),
            'event_type' => $this->faker->randomElement(['filing', 'memo', 'hearing', 'judgment', 'note']),
            'event_date' => now()->toDateString(),
            'description' => $this->faker->sentence(),
        ];
    }
}
