<?php

namespace Database\Factories;

use App\Models\Poll;
use App\Models\PollOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PollOption>
 */
class PollOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'poll_id' => Poll::factory(),
            'text' => fake()->sentence(2),
            'position' => fake()->unique()->numberBetween(1, 1000000),
        ];
    }
}
