<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'block' => fake()->optional()->randomElement(['A', 'B', 'C']),
            'number' => fake()->unique()->numerify('###'),
            'type' => fake()->randomElement(['lote', 'casa', 'apartamento']),
            'complement' => fake()->optional()->sentence(3),
            'status' => 'active',
        ];
    }
}
