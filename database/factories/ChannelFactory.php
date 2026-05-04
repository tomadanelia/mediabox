<?php

namespace Database\Factories;

use App\Models\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Channel>
 */
class ChannelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
{
    return [
        'external_id' => (string) $this->faker->unique()->numberBetween(100, 999),
        'name' => $this->faker->word . ' TV',
        'number' => $this->faker->unique()->numberBetween(1, 500),
        'is_active' => true,
        'is_free' => false,
    ];
}
}
