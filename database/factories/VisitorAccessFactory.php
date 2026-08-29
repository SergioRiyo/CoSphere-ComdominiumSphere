<?php

namespace Database\Factories;

use App\Enums\VisitorAccessStatus;
use App\Enums\VisitorAuthorizationStatus;
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
        return [
            'visitor_authorization_id' => VisitorAuthorization::factory()->active(),
            'doorman_id' => User::factory()->porteiro(),
            'exit_doorman_id' => null,
            'entry_time' => now()->subMinutes(30),
            'exit_time' => null,
            'validation_status' => VisitorAccessStatus::Validated,
            'observations' => fake()->optional()->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'entry_time' => null,
            'exit_time' => null,
            'exit_doorman_id' => null,
            'validation_status' => VisitorAccessStatus::Pending,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'entry_time' => null,
            'exit_time' => null,
            'exit_doorman_id' => null,
            'validation_status' => VisitorAccessStatus::Rejected,
        ]);
    }

    public function open(): static
    {
        return $this->state(fn (): array => [
            'entry_time' => now()->subMinutes(30),
            'exit_time' => null,
            'exit_doorman_id' => null,
            'validation_status' => VisitorAccessStatus::Validated,
        ]);
    }

    public function closed(?User $exitDoorman = null): static
    {
        return $this
            ->state(fn (): array => [
                'entry_time' => now()->subHours(2),
                'exit_time' => now()->subHour(),
                'exit_doorman_id' => $exitDoorman?->id
                    ?? User::factory()->porteiro(),
                'validation_status' => VisitorAccessStatus::Validated,
            ])
            ->afterCreating(function (VisitorAccess $access): void {
                $access->visitorAuthorization()->update([
                    'status' => VisitorAuthorizationStatus::Used,
                ]);
            });
    }

    public function withDifferentDoormen(): static
    {
        return $this->closed();
    }
}
