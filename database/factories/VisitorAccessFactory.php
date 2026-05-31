<?php

namespace Database\Factories;

use App\Enums\VisitorAccessStatus;
use App\Models\User;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitorAccess>
 */
class VisitorAccessFactory extends Factory
{
    protected $model = VisitorAccess::class;

    public function definition(): array
    {
        $entryTime = fake()->dateTimeBetween('-7 days', 'now');

        return [
            'visitor_authorization_id' => VisitorAuthorization::query()->inRandomOrder()->first()?->getKey()
                ?? VisitorAuthorization::factory(),

            'doorman_id' => User::query()->inRandomOrder()->first()?->getKey()
                ?? User::factory(),

            'entry_time' => $entryTime,

            'exit_time' => fake()->boolean(60)
                ? (clone $entryTime)->modify('+2 hours')
                : null,

            'validation_status' => fake()->randomElement(VisitorAccessStatus::cases())->value,

            'observations' => fake()->optional()->sentence(),
        ];
    }
}
