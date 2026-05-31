<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\CommonArea;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReservationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Reservation
    {
        return DB::transaction(function () use ($data) {
            $commonArea = CommonArea::query()
                ->lockForUpdate()
                ->findOrFail($data['common_area_id']);

            [$startsAt, $endsAt] = $this->parseRequestedPeriod($data);

            $this->ensureCommonAreaIsAvailable($commonArea);
            $this->ensureRequestedScheduleIsValid($commonArea, $startsAt, $endsAt);
            $this->ensurePeriodHasNoConflict($commonArea, $startsAt, $endsAt);

            return Reservation::create([
                'common_area_id' => $commonArea->id,
                'user_id' => $data['user_id'],
                'unit_id' => $data['unit_id'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $commonArea->requires_approval
                    ? ReservationStatus::Pending
                    : ReservationStatus::Approved,
            ]);
        });
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
        try {
            return [
                Carbon::parse($data['starts_at']),
                Carbon::parse($data['ends_at']),
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
        $hasConflict = Reservation::query()
            ->where('common_area_id', $commonArea->id)
            ->whereIn('status', [
                ReservationStatus::Pending->value,
                ReservationStatus::Approved->value,
            ])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($hasConflict) {
            throw ValidationException::withMessages([
                'starts_at' => 'Ja existe uma reserva para esta area no horario informado.',
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
