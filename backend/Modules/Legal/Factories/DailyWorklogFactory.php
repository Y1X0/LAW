<?php

namespace Modules\Legal\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\DailyWorklog;

/**
 * @extends Factory<DailyWorklog>
 */
class DailyWorklogFactory extends Factory
{
    protected $model = DailyWorklog::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'work_date' => now()->toDateString(),
            'done_today' => $this->faker->sentence(),
            'plan_tomorrow' => $this->faker->sentence(),
        ];
    }
}
