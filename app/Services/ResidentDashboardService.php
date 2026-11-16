<?php

namespace App\Services;

use App\Enums\IncidentStatus;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\Announcement;
use App\Models\FinancialCharge;
use App\Models\Incident;
use App\Models\Order;
use App\Models\Poll;
use App\Models\Reservation;
use App\Models\User;

class ResidentDashboardService
{
    public function __construct(
        private readonly AnnouncementQueryService $announcements,
        private readonly PollQueryService $polls,
    ) {}

    /** @return array<string, mixed> */
    public function overview(User $resident): array
    {
        $reservations = Reservation::query()->where('user_id', $resident->id)
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Approved])
            ->where('starts_at', '>=', now());
        $nextReservation = (clone $reservations)->with('commonArea:id,name')
            ->orderBy('starts_at')->orderBy('id')->first();
        $financial = $resident->unit_id === null ? null : FinancialCharge::query()
            ->where('unit_id', $resident->unit_id)->where('status', 'open')
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(amount_cents), 0) as amount')->first();

        return [
            'orders_pending' => $resident->unit_id === null ? null : Order::query()
                ->where('unit_id', $resident->unit_id)->where('status', OrderStatus::ReceivedAtGate)->count(),
            'reservations_count' => $reservations->count(),
            'next_reservation' => $nextReservation === null ? null : [
                'id' => $nextReservation->id,
                'area' => $nextReservation->commonArea?->name,
                'starts_at' => $nextReservation->starts_at->toIso8601String(),
                'status' => $nextReservation->status->label(),
            ],
            'open_amount_cents' => $financial === null ? null : (int) $financial->amount,
            'open_charges_count' => $financial === null ? null : (int) $financial->total,
            'incidents_open' => Incident::query()->where('resident_id', $resident->id)
                ->whereIn('status', [IncidentStatus::Open, IncidentStatus::InProgress])->count(),
            'announcements' => $this->announcements->recentForResident($resident)
                ->map(fn (Announcement $announcement): array => [
                    'id' => $announcement->id, 'title' => $announcement->title,
                    'published_at' => $announcement->published_at->toIso8601String(),
                ])->all(),
            'polls' => $this->polls->recentOpenForResident($resident)
                ->map(fn (Poll $poll): array => [
                    'id' => $poll->id, 'question' => $poll->question,
                    'ends_at' => $poll->ends_at?->toIso8601String(), 'has_voted' => (bool) $poll->has_voted,
                ])->all(),
        ];
    }
}
