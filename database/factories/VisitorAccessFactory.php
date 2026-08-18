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

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $entryTime = fake()->dateTimeBetween('-7 days', 'now');

        return [
            'visitor_authorization_id' => fn (): int => VisitorAuthorization::factory()->create()->id,

            'doorman_id' => fn (): int => User::factory()->porteiro()->create()->id,

            'entry_time' => $entryTime,

            'exit_time' => fake()->boolean(60)
                ? (clone $entryTime)->modify('+2 hours')
                : null,

            'validation_status' => fake()->randomElement(VisitorAccessStatus::cases())->value,

            'observations' => fake()->optional()->sentence(),
        ];
    }
}
