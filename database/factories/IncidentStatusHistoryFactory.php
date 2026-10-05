<?php

namespace Database\Factories;

use App\Enums\IncidentStatus;
use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\IncidentStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IncidentStatusHistory> */
class IncidentStatusHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory()->inProgress(),
            'from_status' => IncidentStatus::Open,
            'to_status' => IncidentStatus::InProgress,
            'changed_by_user_id' => User::factory()->admin(),
            'actor_role' => UserRole::Admin,
            'reason' => null,
        ];
    }
}
