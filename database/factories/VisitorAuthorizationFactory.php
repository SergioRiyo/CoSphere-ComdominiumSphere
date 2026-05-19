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

    public function definition(): array
    {
        return [
            'visitor_id' => Visitor::factory(),
            'unit_id' => Unit::query()->inRandomOrder()->value('id'),
            'resident_id' => User::query()->inRandomOrder()->value('id'),

            'vehicle_plate' => strtoupper(fake()->bothify('???#?##')),
            'access_code' => strtoupper(Str::random(10)),
            'qr_code' => null,
            'registration_link' => fake()->url(),
            'start_date' => now(),
            'end_date' => now()->addHours(8),
            'status' => 'ativo',
            'authorized_date' => now(),
        ];
    }
}
