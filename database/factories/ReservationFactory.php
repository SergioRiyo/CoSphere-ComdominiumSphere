<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
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
            'unit_id' => fn (): int => Unit::factory()->create()->id,
            'user_id' => fn (array $attributes): int => User::factory()
                ->morador()
                ->create(['unit_id' => $attributes['unit_id']])
                ->id,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
            'status' => ReservationStatus::Approved,
        ];
    }
}
