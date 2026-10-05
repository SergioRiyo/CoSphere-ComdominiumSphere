<?php

namespace Database\Factories;

use App\Enums\MaintenanceRequestStatus;
use App\Enums\UserRole;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenanceRequestStatusHistory> */
class MaintenanceRequestStatusHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'maintenance_request_id' => MaintenanceRequest::factory()->scheduled(),
            'from_status' => MaintenanceRequestStatus::Pending,
            'to_status' => MaintenanceRequestStatus::Scheduled,
            'changed_by_user_id' => User::factory()->admin(),
            'actor_role' => UserRole::Admin,
            'reason' => null,
        ];
    }
}
