<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReservationService
{
    /**
     * Null boundaries represent the beginning/end of the day, without adding
     * opening/closing restrictions to areas with no configured schedule.
     *
     * @return array{date: string, occupied_periods: list<array{start: ?string, end: ?string}>, free_periods: list<array{start: ?string, end: ?string}>}
     */
    public function availability(CommonArea $commonArea, Carbon $date): array
    {
        $this->ensureCommonAreaIsAvailable($commonArea);
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $dayStart->copy()->addDay();
        $opening = $commonArea->available_from === null
            ? $dayStart : Carbon::parse($date->toDateString().' '.$commonArea->available_from);
        $closing = $commonArea->available_until === null
            ? $dayEnd : Carbon::parse($date->toDateString().' '.$commonArea->available_until);

        $reservations = $this->conflictingReservations($commonArea, $dayStart, $dayEnd)
            ->orderBy('starts_at')->orderBy('ends_at')->get(['starts_at', 'ends_at']);
        $occupied = [];
        $free = [];
        $cursor = $opening->copy();

        foreach ($reservations as $reservation) {
            $start = $reservation->starts_at->max($opening);
            $end = $reservation->ends_at->min($closing);

            if ($start->greaterThanOrEqualTo($end)) {
                continue;
            }

            $occupied[] = $this->availabilityPeriod($start, $end, $dayStart, $dayEnd);

            if ($cursor->lessThan($start)) {
                $free[] = $this->availabilityPeriod($cursor, $start, $dayStart, $dayEnd);
            }

            $cursor = $cursor->max($end);
        }

        if ($cursor->lessThan($closing)) {
            $free[] = $this->availabilityPeriod($cursor, $closing, $dayStart, $dayEnd);
        }

        return ['date' => $date->toDateString(), 'occupied_periods' => $occupied, 'free_periods' => $free];
    }

    /** @return array{start: ?string, end: ?string} */
    private function availabilityPeriod(CarbonInterface $start, CarbonInterface $end, CarbonInterface $dayStart, CarbonInterface $dayEnd): array
    {
        return [
            'start' => $start->equalTo($dayStart) ? null : $start->format('H:i:s'),
            'end' => $end->equalTo($dayEnd) ? null : $end->format('H:i:s'),
        ];
    }

    /** @return Builder<Reservation> */
    private function conflictingReservations(CommonArea $commonArea, Carbon $startsAt, Carbon $endsAt): Builder
    {
        return Reservation::query()
            ->where('common_area_id', $commonArea->id)
            ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Approved->value])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $resident, array $data): Reservation
    {
        return DB::transaction(function () use ($resident, $data) {
            Validator::make($data, ['common_area_id' => ['required', 'integer']])->validate();
            $commonArea = CommonArea::query()
                ->lockForUpdate()
                ->find($data['common_area_id']);

            if ($commonArea === null) {
                throw ValidationException::withMessages(['common_area_id' => 'A área selecionada não existe mais.']);
            }

            $resident = $this->resolveResident($resident);

            [$startsAt, $endsAt] = $this->parseRequestedPeriod($data);

            $this->ensureCommonAreaIsAvailable($commonArea);
            $this->ensureRequestedScheduleIsValid($commonArea, $startsAt, $endsAt);
            $this->ensurePeriodHasNoConflict($commonArea, $startsAt, $endsAt);

            return Reservation::create([
                'common_area_id' => $commonArea->id,
                'user_id' => $resident->id,
                'unit_id' => $resident->unit_id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $commonArea->requires_approval
                    ? ReservationStatus::Pending
                    : ReservationStatus::Approved,
            ]);
        });
    }

    private function resolveResident(User $resident): User
    {
        $currentResident = $resident->exists
            ? User::query()->lockForUpdate()->find($resident->getKey()) : null;

        if ($currentResident === null
            || $currentResident->role !== UserRole::Morador
            || ! $currentResident->is_active
            || ! $currentResident->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'reservation' => 'Somente moradores ativos e verificados podem solicitar reservas.',
            ]);
        }

        if ($currentResident->unit_id === null || ! $currentResident->unit()->exists()) {
            throw ValidationException::withMessages([
                'reservation' => 'Seu cadastro não possui uma unidade válida vinculada. Contate a administração.',
            ]);
        }

        return $currentResident;
    }

    public function approve(Reservation $reservation): Reservation
    {
        return $this->updateStatus($reservation, ReservationStatus::Approved);
    }

    public function reject(Reservation $reservation, ?string $reason = null): Reservation
    {
        $reservation->update([
            'status' => ReservationStatus::Rejected,
            'rejection_reason' => $reason,
        ]);

        return $reservation->refresh();
    }

    public function cancel(Reservation $reservation): Reservation
    {
        return $this->updateStatus($reservation, ReservationStatus::Cancelled);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Carbon, 1: Carbon}
     */
    private function parseRequestedPeriod(array $data): array
    {
        $period = [];
        foreach (['starts_at', 'ends_at'] as $field) {
            $value = $data[$field] ?? null;
            $period[$field] = $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value;
        }
        Validator::make($period, [
            'starts_at' => ['required', 'date_format:Y-m-d H:i,Y-m-d H:i:s'],
            'ends_at' => ['required', 'date_format:Y-m-d H:i,Y-m-d H:i:s'],
        ], [
            '*.required' => 'Informe o início e o fim da reserva.',
            '*.date_format' => 'Informe uma data e horário válidos para a reserva.',
        ])->validate();

        try {
            return [
                Carbon::parse($period['starts_at']),
                Carbon::parse($period['ends_at']),
            ];
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'starts_at' => 'Informe um horario valido para a reserva.',
            ]);
        }
    }

    private function ensureCommonAreaIsAvailable(CommonArea $commonArea): void
    {
        if ($commonArea->status !== 'active') {
            throw ValidationException::withMessages([
                'common_area_id' => 'A area comum nao esta disponivel para reservas.',
            ]);
        }
    }

    private function ensureRequestedScheduleIsValid(
        CommonArea $commonArea,
        Carbon $startsAt,
        Carbon $endsAt
    ): void {
        if ($startsAt->greaterThanOrEqualTo($endsAt)) {
            throw ValidationException::withMessages([
                'starts_at' => 'O horario inicial deve ser anterior ao horario final.',
            ]);
        }

        if (! $startsAt->isSameDay($endsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'A reserva deve iniciar e terminar no mesmo dia.',
            ]);
        }

        if ($endsAt->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages([
                'ends_at' => 'Não é possível solicitar uma reserva para um período já encerrado.',
            ]);
        }

        if ($commonArea->available_from !== null) {
            $availableFrom = Carbon::parse($startsAt->toDateString().' '.$commonArea->available_from);

            if ($startsAt->lessThan($availableFrom)) {
                throw ValidationException::withMessages([
                    'starts_at' => 'O horario inicial nao esta disponivel para esta area.',
                ]);
            }
        }

        if ($commonArea->available_until !== null) {
            $availableUntil = Carbon::parse($startsAt->toDateString().' '.$commonArea->available_until);

            if ($endsAt->greaterThan($availableUntil)) {
                throw ValidationException::withMessages([
                    'ends_at' => 'O horario final nao esta disponivel para esta area.',
                ]);
            }
        }

        if (
            $commonArea->max_reservation_minutes !== null
            && $startsAt->diffInMinutes($endsAt) > $commonArea->max_reservation_minutes
        ) {
            throw ValidationException::withMessages([
                'ends_at' => 'A reserva excede a duracao maxima permitida para esta area.',
            ]);
        }
    }

    private function ensurePeriodHasNoConflict(
        CommonArea $commonArea,
        Carbon $startsAt,
        Carbon $endsAt
    ): void {
        $hasConflict = $this->conflictingReservations($commonArea, $startsAt, $endsAt)->exists();

        if ($hasConflict) {
            throw ValidationException::withMessages([
                'starts_at' => 'O horário selecionado não está mais disponível. Escolha outro período.',
            ]);
        }
    }

    private function updateStatus(Reservation $reservation, ReservationStatus $status): Reservation
    {
        $reservation->update([
            'status' => $status,
        ]);

        return $reservation->refresh();
    }
}
