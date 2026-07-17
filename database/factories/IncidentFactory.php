<?php

namespace Database\Factories;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => fn (): int => Unit::factory()->create()->id,
            'resident_id' => fn (array $attributes): int => User::factory()->create([
                'role' => 'morador',
                'unit_id' => $attributes['unit_id'],
            ])->id,

            'title' => fake()->randomElement([
                'Vazamento na área comum',
                'Lâmpada queimada no corredor',
                'Barulho excessivo',
                'Portão com problema',
                'Problema na iluminação',
                'Solicitação de manutenção',
            ]),

            'category' => fake()->randomElement([
                'maintenance',
                'security',
                'cleaning',
                'noise',
                'other',
            ]),

            'description' => fake()->paragraph(),

            'opened_at' => fake()->dateTimeBetween('-30 days', 'now'),

            'status' => fake()->randomElement(IncidentStatus::cases())->value,

            'priority' => fake()->randomElement(IncidentPriority::cases())->value,
        ];
    }
}
