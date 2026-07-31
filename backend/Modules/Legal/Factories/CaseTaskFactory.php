<?php

namespace Modules\Legal\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\CaseTask;

/**
 * @extends Factory<CaseTask>
 */
class CaseTaskFactory extends Factory
{
    protected $model = CaseTask::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->randomElement(['إعداد مذكرة دفاع', 'مراجعة ملف القضية', 'حضور جلسة', 'جمع المستندات']),
            'description' => $this->faker->sentence(),
            'priority' => $this->faker->randomElement(['low', 'normal', 'high']),
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'open',
            'assigned_to' => Employee::factory(),
        ];
    }

    public function done(): static
    {
        return $this->state(fn () => ['status' => 'done', 'completed_at' => now()]);
    }
}
