<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\CommonArea;
use App\Models\Unit;
use App\Models\User;
use App\Services\ReservationService;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReservationFeatureTest extends TestCase
{
    use RefreshDatabase;

    private ReservationService $reservationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reservationService = app(ReservationService::class);
    }

    public function test_reserva_e_salva_no_banco(): void
    {
        [$unit, $resident] = $this->createResident();
        $commonArea = CommonArea::factory()->create([
            'requires_approval' => false,
        ]);
        $startsAt = now()->addDays(5)->setTime(10, 0);
        $endsAt = $startsAt->copy()->addHours(2);

        $reservation = $this->reservationService->create($this->reservationData(
            commonArea: $commonArea,
            unit: $unit,
            resident: $resident,
            attributes: [
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ],
        ));

        $this->assertModelExists($reservation);
        $this->assertSame(ReservationStatus::Approved, $reservation->status);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'common_area_id' => $commonArea->id,
            'user_id' => $resident->id,
            'unit_id' => $unit->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'status' => ReservationStatus::Approved->value,
        ]);
    }

    public function test_nao_permite_reservar_horario_ocupado(): void
    {
        [$unit, $resident] = $this->createResident();
        [$otherUnit, $otherResident] = $this->createResident();
        $commonArea = CommonArea::factory()->create([
            'requires_approval' => false,
        ]);
        $startsAt = now()->addDays(5)->setTime(10, 0);

        $existingReservation = $this->reservationService->create($this->reservationData(
            commonArea: $commonArea,
            unit: $unit,
            resident: $resident,
            attributes: [
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addHours(2),
            ],
        ));

        $this->assertValidationError('starts_at', function () use ($commonArea, $otherUnit, $otherResident, $startsAt): void {
            $this->reservationService->create($this->reservationData(
                commonArea: $commonArea,
                unit: $otherUnit,
                resident: $otherResident,
                attributes: [
                    'starts_at' => $startsAt->copy()->addHour(),
                    'ends_at' => $startsAt->copy()->addHours(3),
                ],
            ));
        });

        $this->assertDatabaseCount('reservations', 1);
        $this->assertModelExists($existingReservation);
        $this->assertDatabaseMissing('reservations', [
            'common_area_id' => $commonArea->id,
            'user_id' => $otherResident->id,
            'unit_id' => $otherUnit->id,
        ]);
    }

    public function test_permite_reservas_consecutivas_sem_sobreposicao(): void
    {
        [$unit, $resident] = $this->createResident();
        [$otherUnit, $otherResident] = $this->createResident();
        $commonArea = CommonArea::factory()->create([
            'requires_approval' => false,
        ]);
        $startsAt = now()->addDays(5)->setTime(10, 0);

        $firstReservation = $this->reservationService->create($this->reservationData(
            commonArea: $commonArea,
            unit: $unit,
            resident: $resident,
            attributes: [
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addHours(2),
            ],
        ));

        $secondReservation = $this->reservationService->create($this->reservationData(
            commonArea: $commonArea,
            unit: $otherUnit,
            resident: $otherResident,
            attributes: [
                'starts_at' => $startsAt->copy()->addHours(2),
                'ends_at' => $startsAt->copy()->addHours(4),
            ],
        ));

        $this->assertModelExists($firstReservation);
        $this->assertModelExists($secondReservation);
        $this->assertDatabaseCount('reservations', 2);
    }

    /**
     * @return array{0: Unit, 1: User}
     */
    private function createResident(): array
    {
        $unit = Unit::factory()->create();
        $resident = User::factory()->create([
            'unit_id' => $unit->id,
            'role' => 'morador',
        ]);

        return [$unit, $resident];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function reservationData(
        CommonArea $commonArea,
        Unit $unit,
        User $resident,
        array $attributes = []
    ): array {
        $startsAt = now()->addDays(5)->setTime(10, 0);

        return [
            'common_area_id' => $commonArea->id,
            'user_id' => $resident->id,
            'unit_id' => $unit->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            ...$attributes,
        ];
    }

    private function assertValidationError(string $key, Closure $callback): void
    {
        try {
            $callback();
            $this->fail("Era esperado um erro de validacao para [{$key}].");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors());
        }
    }
}
