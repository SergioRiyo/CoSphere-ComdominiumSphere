<?php

namespace Database\Factories;

use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'user_id' => User::factory(),
            'plate' => strtoupper(fake()->unique()->bothify('???#?##')),
            'model' => fake()->bothify('Modelo ##'),
            'color' => fake()->safeColorName(),
        ];
    }
}
