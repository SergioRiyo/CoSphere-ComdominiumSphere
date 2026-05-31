<?php

namespace Database\Factories;

use App\Models\CommonArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommonArea>
 */
class CommonAreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'available_from' => '08:00',
            'available_until' => '22:00',
            'max_reservation_minutes' => 240,
            'rules' => fake()->sentence(),
            'is_active' => true,
            'requires_approval' => true,
        ];
    }
}
