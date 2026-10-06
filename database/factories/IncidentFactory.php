<?php

namespace Database\Factories;

use App\Enums\IncidentCategory;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Incident> */
class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        return [
            'resident_id' => fn (array $attributes): int => User::factory()->morador()->create(
                is_numeric($attributes['unit_id'] ?? null) ? ['unit_id' => $attributes['unit_id']] : [],
            )->id,
            'unit_id' => fn (array $attributes): int => User::query()->findOrFail($attributes['resident_id'])->unit_id,
            'title' => fake()->sentence(),
            'type' => IncidentType::Incident,
            'category' => fake()->randomElement(IncidentCategory::cases())->value,
            'description' => fake()->paragraph(),
            'opened_at' => now(),
            'status' => IncidentStatus::Open,
            'priority' => IncidentPriority::Medium,
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => IncidentStatus::Open]);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => IncidentStatus::InProgress]);
    }

    public function completed(): static
    {
        return $this->state(['status' => IncidentStatus::Completed]);
    }

    public function canceled(): static
    {
        return $this->state(['status' => IncidentStatus::Canceled]);
    }

    public function maintenanceRequest(): static
    {
        return $this->state(['type' => IncidentType::MaintenanceRequest]);
    }
}
