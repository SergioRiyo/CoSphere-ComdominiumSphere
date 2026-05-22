<?php

namespace Database\Factories;

use App\Enums\MaintenanceRequestStatus;
use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaintenanceRequestFactory extends Factory
{
    protected $model = MaintenanceRequest::class;

    public function definition(): array
    {
        return [
            'incident_id' => Incident::query()->inRandomOrder()->value('id'),

            'service_provider_id' => ServiceProvider::query()
                ->inRandomOrder()
                ->value('id'),

            'admin_id' => User::query()
                ->inRandomOrder()
                ->value('id'),

            'description' => fake()->paragraph(),

            'scheduled_at' => fake()->optional()->dateTimeBetween('now', '+15 days'),

            'executed_at' => fake()->optional()->dateTimeBetween('-15 days', 'now'),

            'cost' => fake()->optional()->randomFloat(2, 80, 1500),

            'status' => fake()->randomElement(MaintenanceRequestStatus::cases())->value,
        ];
    }
}
