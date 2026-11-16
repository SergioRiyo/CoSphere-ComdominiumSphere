<?php

namespace Database\Factories;

use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PollVote>
 */
class PollVoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'poll_option_id' => PollOption::factory(),
            'poll_id' => fn (array $attributes): int => PollOption::findOrFail($attributes['poll_option_id'])->poll_id,
            'user_id' => User::factory()->morador(),
        ];
    }
}
