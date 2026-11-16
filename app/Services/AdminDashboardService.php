<?php

namespace App\Services;

use App\Enums\IncidentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\Reservation;
use App\Models\Unit;
use App\Models\User;

class AdminDashboardService
{
    /** @return array<string, mixed> */
    public function overview(User $admin): array
    {
        return [
            'pending_reservations' => Reservation::query()->where('status', ReservationStatus::Pending)->count(),
            'open_incidents' => Incident::query()->where('status', IncidentStatus::Open)->count(),
            'upcoming_reservations' => Reservation::query()
                ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Approved])
                ->where('starts_at', '>=', now())
                ->with(['commonArea:id,name', 'unit:id,block,number'])
                ->orderBy('starts_at')->orderBy('id')->limit(4)
                ->get(['id', 'common_area_id', 'unit_id', 'starts_at', 'status'])
                ->map(fn (Reservation $reservation): array => [
                    'id' => $reservation->id,
                    'area' => $reservation->commonArea?->name,
                    'unit' => $reservation->unit ? implode(' · ', array_filter([$reservation->unit->block, $reservation->unit->number])) : null,
                    'starts_at' => $reservation->starts_at->toIso8601String(),
                    'status' => $reservation->status->label(),
                ])->all(),
            'recent_notifications' => $admin->notifications()->orderByDesc('sent_at')->orderByDesc('id')->limit(4)
                ->get(['id', 'title', 'message', 'sent_at', 'is_read'])
                ->map(fn ($notification): array => [
                    'id' => $notification->id, 'title' => $notification->title, 'message' => $notification->message,
                    'sent_at' => $notification->sent_at?->toIso8601String(), 'is_read' => $notification->is_read,
                ])->all(),
        ];
    }

    /**
     * @return array{
     *     active_users: int,
     *     inactive_users: int,
     *     administrators: int,
     *     residents: int,
     *     doormen: int,
     *     units: int
     * }
     */
    public function metrics(): array
    {
        $userMetrics = User::query()
            ->toBase()
            ->selectRaw(
                <<<'SQL'
                    COUNT(CASE WHEN is_active THEN 1 END) AS active_users,
                    COUNT(CASE WHEN NOT is_active THEN 1 END) AS inactive_users,
                    COUNT(CASE WHEN role = ? THEN 1 END) AS administrators,
                    COUNT(CASE WHEN role = ? THEN 1 END) AS residents,
                    COUNT(CASE WHEN role = ? THEN 1 END) AS doormen
                SQL,
                [
                    UserRole::Admin->value,
                    UserRole::Morador->value,
                    UserRole::Porteiro->value,
                ],
            )
            ->first();

        return [
            'active_users' => (int) $userMetrics->active_users,
            'inactive_users' => (int) $userMetrics->inactive_users,
            'administrators' => (int) $userMetrics->administrators,
            'residents' => (int) $userMetrics->residents,
            'doormen' => (int) $userMetrics->doormen,
            'units' => Unit::query()->count(),
        ];
    }
}
