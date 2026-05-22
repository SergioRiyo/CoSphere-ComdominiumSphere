<?php

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recipient_id' => User::factory(),
            'title' => $this->faker->sentence(4),
            'message' => $this->faker->paragraph(),
            'type' => $this->faker->randomElement([
                NotificationType::Reservation,
                NotificationType::Visitor,
                NotificationType::Package,
                NotificationType::Occurrence,
                NotificationType::System,
            ]),
            'sent_at' => now(),
            'is_read' => $this->faker->boolean(30),
        ];
    }
}