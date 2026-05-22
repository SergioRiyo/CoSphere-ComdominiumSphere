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

    public function definition(): array
    {
        return [
            'unit_id' => Unit::query()->inRandomOrder()->value('id'),
            'resident_id' => User::query()->inRandomOrder()->value('id'),

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
