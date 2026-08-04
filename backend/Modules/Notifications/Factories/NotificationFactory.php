<?php

namespace Modules\Notifications\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Notifications\Models\Notification;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'generic',
            'title' => $this->faker->sentence(3),
            'body' => $this->faker->sentence(),
            'related_type' => null,
            'related_id' => null,
            'read_at' => null,
        ];
    }

    /** حالة «مقروء». */
    public function read(): static
    {
        return $this->state(fn () => ['read_at' => now()]);
    }
}
