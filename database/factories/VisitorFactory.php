<?php

namespace Database\Factories;

use App\Models\Unit;
use App\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visitor>
 */
class VisitorFactory extends Factory
{
    protected $model = Visitor::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'name' => fake()->name(),
            'cpf' => fake()->unique()->numerify('###.###.###-##'),
            'phone' => fake()->phoneNumber(),
        ];
    }
}
