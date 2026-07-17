<?php

namespace Tests\Unit;

use App\Enums\ReservationStatus;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\Unit;
use App\Models\User;
use App\Services\ReservationService;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReservationService $reservationService;

    private Unit $unit;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reservationService = app(ReservationService::class);
        $this->unit = Unit::factory()->create();
        $this->user = User::factory()->create([
            'unit_id' => $this->unit->id,
        ]);
    }

    public function test_deve_aprovar_reserva_quando_area_nao_exige_aprovacao(): void
    {
        $reservation = $this->reservationService->create(
            $this->reservationData($this->createCommonArea([
                'requires_approval' => false,
            ])),
        );

        $this->assertSame(ReservationStatus::Approved, $reservation->status);
    }

    public function test_deve_deixar_reserva_pendente_quando_area_exige_aprovacao(): void
    {
        $reservation = $this->reservationService->create(
            $this->reservationData($this->createCommonArea([
                'requires_approval' => true,
            ])),
        );

        $this->assertSame(ReservationStatus::Pending, $reservation->status);
    }

    public function test_deve_impedir_reserva_de_area_inativa(): void
    {
        $commonArea = $this->createCommonArea([
            'status' => 'inactive',
        ]);

        $this->assertValidationError('common_area_id', function () use ($commonArea): void {
            $this->reservationService->create($this->reservationData($commonArea));
        });
    }

    public function test_deve_impedir_horario_invalido(): void
    {
        $commonArea = $this->createCommonArea();
        $startsAt = now()->addDay()->setTime(12, 0);

        $this->assertValidationError('starts_at', function () use ($commonArea, $startsAt): void {
            $this->reservationService->create($this->reservationData($commonArea, [
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->subHour(),
            ]));
        });
    }

    public function test_deve_impedir_conflito_de_horario(): void
    {
        $commonArea = $this->createCommonArea();
        $startsAt = now()->addDay()->setTime(10, 0);

        $this->reservationService->create($this->reservationData($commonArea, [
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
        ]));

        $this->assertValidationError('starts_at', function () use ($commonArea, $startsAt): void {
            $this->reservationService->create($this->reservationData($commonArea, [
                'starts_at' => $startsAt->copy()->addHour(),
                'ends_at' => $startsAt->copy()->addHours(3),
            ]));
        });
    }

    public function test_deve_impedir_reserva_em_area_em_manutencao(): void
    {
        $commonArea = $this->createCommonArea([
            'status' => 'maintenance',
            'maintenance_reason' => 'Manutencao eletrica.',
        ]);

        $this->assertValidationError('common_area_id', function () use ($commonArea): void {
            $this->reservationService->create($this->reservationData($commonArea));
        });
    }

    public function test_deve_aprovar_recusar_e_cancelar_reservas(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Pending,
        ]);

        $reservation = $this->reservationService->approve($reservation);
        $this->assertSame(ReservationStatus::Approved, $reservation->status);

        $reservation = $this->reservationService->reject($reservation, 'Documentacao incompleta.');
        $this->assertSame(ReservationStatus::Rejected, $reservation->status);
        $this->assertSame('Documentacao incompleta.', $reservation->rejection_reason);

        $reservation = $this->reservationService->cancel($reservation);
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createCommonArea(array $attributes = []): CommonArea
    {
        return CommonArea::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function reservationData(CommonArea $commonArea, array $attributes = []): array
    {
        $startsAt = now()->addDay()->setTime(10, 0);

        return [
            'common_area_id' => $commonArea->id,
            'user_id' => $this->user->id,
            'unit_id' => $this->unit->id,
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
