<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ReservationQueryService
{
    /** @return Builder<Reservation> */
    private function accessibleReservations(User $user): Builder
    {
        abort_unless($user->is_active && $user->hasVerifiedEmail()
            && in_array($user->role, [UserRole::Admin, UserRole::Morador], true), 403);

        return Reservation::query()
            ->with($user->role === UserRole::Admin
                ? ['commonArea:id,name', 'user:id,name', 'unit:id,block,number'] : ['commonArea:id,name'])
            ->when($user->role === UserRole::Morador, fn (Builder $query) => $query->where('user_id', $user->id));
    }

    /**
     * @param  array{status?: ?string, date_from?: ?string, date_to?: ?string, page?: mixed}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        $query = $this->accessibleReservations($user)
            ->select(['id', 'common_area_id', 'user_id', 'unit_id', 'starts_at', 'ends_at', 'status']);
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['date_from'])) {
            $query->where('starts_at', '>=', CarbonImmutable::parse($filters['date_from'])->startOfDay());
        }
        if (! empty($filters['date_to'])) {
            $query->where('starts_at', '<', CarbonImmutable::parse($filters['date_to'])->addDay()->startOfDay());
        }

        return $query->orderByDesc('starts_at')->orderByDesc('id')->paginate(15)->appends($filters)
            ->through(fn (Reservation $reservation): array => $this->serialize($reservation, $user));
    }

    /** @return array<string, mixed> */
    public function details(User $user, int $id): array
    {
        $reservation = $this->accessibleReservations($user)->findOrFail($id);
        $reservation->load($user->role === UserRole::Admin ? ['statusHistory.changedBy:id,name'] : ['statusHistory']);

        return $this->serialize($reservation, $user) + [
            'rejection_reason' => $reservation->rejection_reason,
            'history' => $reservation->statusHistory->map(fn (ReservationStatusHistory $history): array => [
                'id' => $history->id,
                'from_status' => $history->from_status?->value,
                'from_label' => $history->from_status?->label(),
                'to_status' => $history->to_status->value,
                'to_label' => $history->to_status->label(),
                'actor' => $user->role === UserRole::Admin
                    ? $history->changedBy->name.' ('.$history->actor_role->label().')'
                    : ($history->changed_by_user_id === $user->id ? 'Você' : $history->actor_role->label()),
                'reason' => $history->reason,
                'created_at' => $history->created_at->format('Y-m-d H:i:s'),
            ])->all(),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    public function statuses(): array
    {
        return array_map(fn (ReservationStatus $status): array => ['value' => $status->value, 'label' => $status->label()], ReservationStatus::cases());
    }

    /** @return array<string, mixed> */
    private function serialize(Reservation $reservation, User $user): array
    {
        $admin = $user->role === UserRole::Admin;

        return [
            'id' => $reservation->id,
            'area' => $reservation->commonArea->name,
            'date' => $reservation->starts_at->toDateString(),
            'start' => $reservation->starts_at->format('H:i:s'),
            'end' => $reservation->ends_at->format('H:i:s'),
            'status' => $reservation->status->value,
            'status_label' => $reservation->status->label(),
            'can_cancel' => in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Approved], true)
                && ($admin || $reservation->starts_at->greaterThan(now())),
            ...($admin ? [
                'resident' => $reservation->user->name,
                'unit' => $reservation->unit->only(['block', 'number']),
            ] : []),
        ];
    }
}
