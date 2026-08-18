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
            'unit_id' => fn (): int => Unit::factory()->create()->id,
            'user_id' => fn (array $attributes): int => User::factory()
                ->morador()
                ->create(['unit_id' => $attributes['unit_id']])
                ->id,
            'plate' => strtoupper(fake()->unique()->bothify('???#?##')),
            'model' => fake()->bothify('Modelo ##'),
            'color' => fake()->safeColorName(),
        ];
    }
}
