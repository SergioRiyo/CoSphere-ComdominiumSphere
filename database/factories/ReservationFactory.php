<?php

namespace Database\Factories;

use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('now', '+30 days');

        return [
            'common_area_id' => CommonArea::factory(),
            'user_id' => User::factory(),
            'unit_id' => Unit::factory(),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
            'status' => 'confirmed',
        ];
    }
}
