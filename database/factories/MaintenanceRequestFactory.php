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

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incident_id' => fn (): int => Incident::factory()->create()->id,

            'service_provider_id' => fn (): int => ServiceProvider::factory()->create()->id,

            'admin_id' => fn (): int => User::factory()->admin()->create()->id,

            'description' => fake()->paragraph(),

            'scheduled_at' => fake()->optional()->dateTimeBetween('now', '+15 days'),

            'executed_at' => fake()->optional()->dateTimeBetween('-15 days', 'now'),

            'cost' => fake()->optional()->randomFloat(2, 80, 1500),

            'status' => fake()->randomElement(MaintenanceRequestStatus::cases())->value,
        ];
    }
}
