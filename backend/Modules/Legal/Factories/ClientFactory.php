<?php

namespace Modules\Legal\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Legal\Models\Client;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'type' => 'company',
            'phone' => $this->faker->numerify('07########'),
            'email' => $this->faker->safeEmail(),
            'national_id' => (string) $this->faker->numerify('##########'),
            'address' => $this->faker->address(),
            'status' => 'active',
            'notes' => null,
        ];
    }

    public function individual(): static
    {
        return $this->state(fn () => ['type' => 'individual', 'name' => $this->faker->name()]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
