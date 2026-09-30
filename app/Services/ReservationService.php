<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\CommonArea;
use App\Models\CommonAreaBlock;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReservationService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Null boundaries represent the beginning/end of the day, without adding
     * opening/closing restrictions to areas with no configured schedule.
     *
     * @return array{date: string, occupied_periods: list<array{start: ?string, end: ?string}>, blocked_periods: list<array{start: ?string, end: ?string}>, free_periods: list<array{start: ?string, end: ?string}>}
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
        $blocks = CommonAreaBlock::query()->conflicting($commonArea, $dayStart, $dayEnd)
            ->get(['starts_at', 'ends_at']);
        $periods = $reservations->toBase()->concat($blocks)->sortBy('starts_at');
        $occupied = [];
        $blocked = [];
        $free = [];
        $cursor = $opening->copy();

        foreach ($periods as $reservation) {
            $start = $reservation->starts_at->max($opening);
            $end = $reservation->ends_at->min($closing);

            if ($start->greaterThanOrEqualTo($end)) {
                continue;
            }

            $period = $this->availabilityPeriod($start, $end, $dayStart, $dayEnd);
            if ($reservation instanceof CommonAreaBlock) {
                $blocked[] = $period;
            } else {
                $occupied[] = $period;
            }

            if ($cursor->lessThan($start)) {
                $free[] = $this->availabilityPeriod($cursor, $start, $dayStart, $dayEnd);
            }

            $cursor = $cursor->max($end);
        }

        if ($cursor->lessThan($closing)) {
            $free[] = $this->availabilityPeriod($cursor, $closing, $dayStart, $dayEnd);
        }

        return ['date' => $date->toDateString(), 'occupied_periods' => $occupied, 'blocked_periods' => $blocked, 'free_periods' => $free];
    }

    public function hasConflictingReservations(CommonArea $area, Carbon $start, Carbon $end): bool
    {
        return $this->conflictingReservations($area, $start, $end)->exists();
    }

    private function ensurePeriodHasNoBlock(CommonArea $area, Carbon $start, Carbon $end): void
    {
        if (CommonAreaBlock::query()->conflicting($area, $start, $end)->exists()) {
            throw ValidationException::withMessages(['starts_at' => 'O horário selecionado está indisponível.']);
        }
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
            $this->ensurePeriodHasNoBlock($commonArea, $startsAt, $endsAt);

            $reservation = Reservation::create([
                'common_area_id' => $commonArea->id,
                'user_id' => $resident->id,
                'unit_id' => $resident->unit_id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $commonArea->requires_approval
                    ? ReservationStatus::Pending
                    : ReservationStatus::Approved,
            ]);
            $this->recordStatusHistory($reservation, $resident, null);
            $this->notifyReservation($reservation, $commonArea->name, true);

            return $reservation;
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

    public function approve(User $admin, Reservation $reservation): Reservation
    {
        return $this->transition($admin, $reservation, ReservationStatus::Approved, UserRole::Admin);
    }

    public function reject(User $admin, Reservation $reservation, string $reason): Reservation
    {
        return $this->transition($admin, $reservation, ReservationStatus::Rejected, UserRole::Admin, $reason);
    }

    public function cancelByAdmin(User $admin, Reservation $reservation): Reservation
    {
        return $this->transition($admin, $reservation, ReservationStatus::Cancelled, UserRole::Admin);
    }

    public function cancelByResident(User $resident, Reservation $reservation): Reservation
    {
        return $this->transition($resident, $reservation, ReservationStatus::Cancelled, UserRole::Morador);
    }

    private function transition(User $actor, Reservation $reservation, ReservationStatus $target, UserRole $requiredRole, ?string $reason = null): Reservation
    {
        return DB::transaction(function () use ($actor, $reservation, $target, $requiredRole, $reason): Reservation {
            $current = Reservation::query()->lockForUpdate()->findOrFail($reservation->getKey());
            $area = $target === ReservationStatus::Approved
                ? CommonArea::query()->lockForUpdate()->findOrFail($current->common_area_id) : null;
            $currentActor = $actor->exists ? User::query()->lockForUpdate()->find($actor->getKey()) : null;

            if ($currentActor === null || $currentActor->role !== $requiredRole
                || ! $currentActor->is_active || ! $currentActor->hasVerifiedEmail()
                || ($requiredRole === UserRole::Morador && $current->user_id !== $currentActor->id)) {
                throw new AuthorizationException('Você não tem permissão para alterar esta reserva.');
            }

            $allowed = $target === ReservationStatus::Cancelled
                ? [ReservationStatus::Pending, ReservationStatus::Approved] : [ReservationStatus::Pending];
            if (! in_array($current->status, $allowed, true)) {
                throw ValidationException::withMessages(['reservation' => 'Esta reserva não permite mais a operação solicitada. Atualize a lista.']);
            }

            if ($requiredRole === UserRole::Morador && $current->starts_at->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages(['reservation' => 'O cancelamento pelo morador só é permitido antes do início da reserva.']);
            }

            if ($target === ReservationStatus::Approved) {
                [$start, $end] = $this->parseRequestedPeriod($current->only(['starts_at', 'ends_at']));
                $this->ensureCommonAreaIsAvailable($area);
                $this->ensureRequestedScheduleIsValid($area, $start, $end);
                $this->ensurePeriodHasNoBlock($area, $start, $end);
                if ($this->conflictingReservations($area, $start, $end)->whereKeyNot($current->id)->exists()) {
                    throw ValidationException::withMessages(['reservation' => 'Há outra reserva ocupando este período. Não foi possível aprovar.']);
                }
            }

            $changes = ['status' => $target];
            if ($target === ReservationStatus::Rejected) {
                $validated = Validator::make(['rejection_reason' => trim($reason ?? '')], [
                    'rejection_reason' => ['required', 'string', 'max:255'],
                ], [
                    'rejection_reason.required' => 'Informe o motivo da recusa.',
                    'rejection_reason.max' => 'O motivo deve ter no máximo 255 caracteres.',
                ])->validate();
                $changes['rejection_reason'] = $validated['rejection_reason'];
            }
            $previousStatus = $current->status;
            $current->update($changes);
            $this->recordStatusHistory($current, $currentActor, $previousStatus, $changes['rejection_reason'] ?? null);
            if ($requiredRole === UserRole::Admin) {
                $this->notifyReservation($current, $area?->name ?? $current->commonArea()->value('name'));
            }

            return $current;
        });
    }

    private function notifyReservation(Reservation $reservation, string $areaName, bool $created = false): void
    {
        [$title, $result] = match ($reservation->status) {
            ReservationStatus::Pending => ['Solicitação de reserva registrada', 'foi registrada e aguarda análise'],
            ReservationStatus::Approved => $created
                ? ['Reserva confirmada', 'foi criada e confirmada automaticamente']
                : ['Reserva aprovada', 'foi aprovada'],
            ReservationStatus::Rejected => ['Reserva recusada', 'foi recusada'],
            ReservationStatus::Cancelled => ['Reserva cancelada', 'foi cancelada pela administração'],
        };
        $message = 'Sua reserva de '.$areaName.' para '.$reservation->starts_at->format('d/m/Y')
            .', das '.$reservation->starts_at->format('H:i:s').' às '.$reservation->ends_at->format('H:i:s')
            .', '.$result.'.';
        if ($reservation->status === ReservationStatus::Rejected && $reservation->rejection_reason !== null) {
            $message .= ' Motivo: '.$reservation->rejection_reason;
        }

        $this->notifications->create($reservation->user_id, $title, $message, NotificationType::Reservation);
    }

    private function recordStatusHistory(Reservation $reservation, User $actor, ?ReservationStatus $from, ?string $reason = null): void
    {
        $history = new ReservationStatusHistory([
            'from_status' => $from,
            'to_status' => $reservation->status,
            'actor_role' => $actor->role,
            'reason' => $reason,
        ]);
        $history->reservation()->associate($reservation);
        $history->changedBy()->associate($actor);
        $history->save();
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
}
