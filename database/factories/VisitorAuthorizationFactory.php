<?php

namespace Database\Factories;

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

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('now', '+7 days');
        $endDate = (clone $startDate)->modify('+8 hours');

        return [
            'visitor_id' => fn (): int => Visitor::factory()->create()->id,

            'unit_id' => fn (): int => Unit::factory()->create()->id,

            'resident_id' => fn (array $attributes): int => User::factory()->create([
                'role' => 'morador',
                'unit_id' => $attributes['unit_id'],
            ])->id,

            'vehicle_plate' => strtoupper(fake()->bothify('???#?##')),

            'qr_code' => null,

            'start_date' => $startDate,
            'end_date' => $endDate,

            'status' => fake()->randomElement([
                'pending_data',
                'active',
                'used',
                'expired',
                'canceled',
            ]),

            'registration_link' => fake()->url(),

            'authorized_date' => now(),

            'access_code' => strtoupper(Str::random(10)),
        ];
    }
}
