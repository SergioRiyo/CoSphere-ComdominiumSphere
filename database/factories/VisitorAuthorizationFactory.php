<?php

namespace Database\Factories;

use App\Enums\VisitorAuthorizationStatus;
use App\Models\Unit;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorAuthorization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

use function Illuminate\Support\now;

/**
 * @extends Factory<VisitorAuthorization>
 */
class VisitorAuthorizationFactory extends Factory
{
    protected $model = VisitorAuthorization::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('now', '+7 days');
        $endDate = (clone $startDate)->modify('+8 hours');

        return [
            'visitor_id' => Visitor::query()->inRandomOrder()->first()?->getKey()
                ?? Visitor::factory(),

            'unit_id' => Unit::query()->inRandomOrder()->first()?->getKey()
                ?? Unit::factory(),

            'resident_id' => User::query()->inRandomOrder()->first()?->getKey()
                ?? User::factory(),

            'vehicle_plate' => strtoupper(fake()->bothify('???#?##')),

            'qr_code' => null,

            'start_date' => $startDate,
            'end_date' => $endDate,

            'status' => fake()->randomElement(VisitorAuthorizationStatus::cases())->value,

            'registration_link' => fake()->url(),

            'authorized_date' => now(),

            'access_code' => strtoupper(Str::random(10)),
        ];
    }
}
