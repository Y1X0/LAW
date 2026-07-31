<?php

namespace Modules\Legal\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Legal\Models\Hearing;
use Modules\Legal\Models\LegalCase;

/**
 * @extends Factory<Hearing>
 */
class HearingFactory extends Factory
{
    protected $model = Hearing::class;

    public function definition(): array
    {
        return [
            'case_id' => LegalCase::factory(),
            'scheduled_at' => now()->addDays(7),
            'type' => $this->faker->randomElement(['جلسة تمهيدية', 'مرافعة', 'نطق بالحكم']),
            'status' => 'scheduled',
            'location' => 'قاعة 3',
        ];
    }

    public function past(): static
    {
        return $this->state(fn () => ['scheduled_at' => now()->subDays(7), 'status' => 'held', 'outcome' => 'تم التأجيل لتقديم مذكرات']);
    }

    public function postponed(): static
    {
        return $this->state(fn () => ['status' => 'postponed', 'postponed_to' => now()->addDays(14), 'postponed_reason' => 'طلب الخصم']);
    }
}
