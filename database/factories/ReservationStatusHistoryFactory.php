<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReservationStatusHistory> */
class ReservationStatusHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'from_status' => ReservationStatus::Pending,
            'to_status' => ReservationStatus::Approved,
            'changed_by_user_id' => User::factory()->admin(),
            'actor_role' => UserRole::Admin,
            'reason' => null,
        ];
    }
}
