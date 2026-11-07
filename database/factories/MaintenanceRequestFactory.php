<?php

namespace Database\Factories;

use App\Enums\MaintenanceRequestStatus;
use App\Models\CommonArea;
use App\Models\CommonAreaBlock;
use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\ServiceProvider;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenanceRequest> */
class MaintenanceRequestFactory extends Factory
{
    protected $model = MaintenanceRequest::class;

    public function definition(): array
    {
        return [
            'incident_id' => null,
            'resident_id' => fn (array $attributes): int => $attributes['incident_id'] !== null
                ? Incident::withTrashed()->findOrFail($attributes['incident_id'])->resident_id
                : User::factory()->morador()->create(
                    is_numeric($attributes['unit_id'] ?? null) ? ['unit_id' => $attributes['unit_id']] : [],
                )->id,
            'unit_id' => function (array $attributes): int {
                if ($attributes['incident_id'] !== null) {
                    return Incident::withTrashed()->findOrFail($attributes['incident_id'])->unit_id;
                }

                return User::query()->findOrFail($attributes['resident_id'])->unit_id;
            },
            'common_area_id' => null,
            'common_area_block_id' => null,
            'service_provider_id' => null,
            'admin_id' => null,
            'description' => fake()->paragraph(),
            'scheduled_at' => null,
            'executed_at' => null,
            'cost' => null,
            'status' => MaintenanceRequestStatus::Pending,
        ];
    }

    public function pendingWithoutProvider(): static
    {
        return $this->state([
            'status' => MaintenanceRequestStatus::Pending, 'service_provider_id' => null,
            'scheduled_at' => null, 'executed_at' => null, 'cost' => null,
        ]);
    }

    public function withoutIncident(): static
    {
        return $this->state(['incident_id' => null]);
    }

    public function administrativeDirect(): static
    {
        return $this->state(['incident_id' => null, 'resident_id' => null, 'unit_id' => Unit::factory(), 'admin_id' => User::factory()->admin()]);
    }

    public function forCommonArea(?CommonArea $area = null): static
    {
        $scheduledAt = now()->addDay()->setTime(10, 0);

        return $this->state(function () use ($area, $scheduledAt): array {
            $commonArea = $area ?? CommonArea::factory()->create();
            $admin = User::factory()->admin()->create();
            $block = CommonAreaBlock::factory()->create([
                'common_area_id' => $commonArea->id,
                'admin_id' => $admin->id,
                'starts_at' => $scheduledAt->copy()->setTimeFromTimeString($commonArea->available_from ?? '00:00'),
                'ends_at' => $commonArea->available_until === null
                    ? $scheduledAt->copy()->addDay()->startOfDay()
                    : $scheduledAt->copy()->setTimeFromTimeString($commonArea->available_until),
            ]);

            return [
                'incident_id' => null,
                'resident_id' => null,
                'unit_id' => null,
                'common_area_id' => $commonArea->id,
                'common_area_block_id' => $block->id,
                'admin_id' => $admin->id,
                'scheduled_at' => $scheduledAt,
            ];
        });
    }

    public function linkedToIncident(?Incident $incident = null): static
    {
        return $this->state(['incident_id' => $incident?->id ?? Incident::factory()]);
    }

    public function scheduled(): static
    {
        return $this->state([
            'status' => MaintenanceRequestStatus::Scheduled,
            'service_provider_id' => ServiceProvider::factory(),
            'admin_id' => User::factory()->admin(),
            'scheduled_at' => now()->addDay(), 'executed_at' => null, 'cost' => null,
        ]);
    }

    public function inProgress(): static
    {
        $scheduledAt = now()->subHour();

        return $this->scheduled()->state([
            'status' => MaintenanceRequestStatus::InProgress,
            'created_at' => $scheduledAt->copy()->subDay(),
            'scheduled_at' => $scheduledAt,
        ]);
    }

    public function completed(): static
    {
        return $this->inProgress()->state([
            'status' => MaintenanceRequestStatus::Completed,
            'executed_at' => now(), 'cost' => fake()->randomFloat(2, 80, 1500),
        ]);
    }

    public function canceled(): static
    {
        return $this->pendingWithoutProvider()->state(['status' => MaintenanceRequestStatus::Canceled]);
    }
}
