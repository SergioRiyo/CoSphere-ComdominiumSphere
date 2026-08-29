<?php

namespace Database\Factories;

use App\Enums\VisitorAuthorizationStatus;
use App\Models\Unit;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'unit_id' => Unit::factory(),
            'resident_id' => function (array $attributes): int {
                return User::factory()->morador()->create([
                    'unit_id' => $attributes['unit_id'],
                ])->id;
            },
            'vehicle_plate' => fake()->optional()->bothify('???-#?##'),
            'access_code' => 'csa_'.Str::random(32),
            'invitation_token_hash' => null,
            'invitation_expires_at' => null,
            'invitation_used_at' => null,
            'start_date' => now()->subHour(),
            'end_date' => now()->addHours(4),
            'status' => VisitorAuthorizationStatus::Active,
            'authorized_date' => now()->subHour(),
        ];
    }

    public function pendingData(?string $rawToken = null): static
    {
        $rawToken ??= Str::random(64);

        return $this->state(fn (): array => [
            'visitor_id' => null,
            'vehicle_plate' => null,
            'access_code' => null,
            'invitation_token_hash' => hash('sha256', $rawToken),
            'invitation_expires_at' => now()->addDay(),
            'invitation_used_at' => null,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'status' => VisitorAuthorizationStatus::PendingData,
            'authorized_date' => null,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => VisitorAuthorizationStatus::Active,
            'start_date' => now()->subHour(),
            'end_date' => now()->addHours(4),
            'authorized_date' => now()->subHour(),
        ]);
    }

    public function future(): static
    {
        return $this->state(fn (): array => [
            'status' => VisitorAuthorizationStatus::Active,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'authorized_date' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => VisitorAuthorizationStatus::Expired,
            'start_date' => now()->subDays(2),
            'end_date' => now()->subDay(),
            'authorized_date' => now()->subDays(2),
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (): array => [
            'status' => VisitorAuthorizationStatus::Canceled,
            'start_date' => now()->subHour(),
            'end_date' => now()->addHours(4),
            'authorized_date' => now()->subHour(),
        ]);
    }

    public function used(): static
    {
        return $this
            ->state(fn (): array => [
                'status' => VisitorAuthorizationStatus::Used,
                'start_date' => now()->subHours(2),
                'end_date' => now()->addHours(2),
                'authorized_date' => now()->subHours(2),
            ])
            ->afterCreating(function (VisitorAuthorization $authorization): void {
                VisitorAccess::factory()->closed()->create([
                    'visitor_authorization_id' => $authorization->id,
                ]);
            });
    }
}
