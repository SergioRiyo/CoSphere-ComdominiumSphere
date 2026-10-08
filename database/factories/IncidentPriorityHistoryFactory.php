<?php

namespace Database\Factories;

use App\Enums\IncidentPriority;
use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\IncidentPriorityHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IncidentPriorityHistory> */
class IncidentPriorityHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory()->state(['priority' => IncidentPriority::High]),
            'changed_by_user_id' => User::factory()->admin(),
            'actor_role' => UserRole::Admin,
            'from_priority' => IncidentPriority::Medium,
            'to_priority' => IncidentPriority::High,
            'created_at' => now(),
        ];
    }
}
