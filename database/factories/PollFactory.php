<?php

namespace Database\Factories;

use App\Models\Poll;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Poll>
 */
class PollFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_id' => User::factory()->admin(),
            'question' => fake()->sentence(),
            'published_at' => null,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => ['published_at' => now()->subMinute(), 'starts_at' => now()->subMinute(), 'ends_at' => null]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['published_at' => now()->subDay(), 'starts_at' => now()->subDay(), 'ends_at' => now()->subMinute()]);
    }
}
