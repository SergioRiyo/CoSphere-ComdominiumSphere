<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenanceRequestChange> */
class MaintenanceRequestChangeFactory extends Factory
{
    public function definition(): array
    {
        return ['maintenance_request_id' => MaintenanceRequest::factory(), 'changed_by_user_id' => User::factory()->admin(),
            'actor_role' => UserRole::Admin, 'changes' => ['description' => ['old' => 'Original', 'new' => 'Atualizada']], 'created_at' => now()];
    }
}
