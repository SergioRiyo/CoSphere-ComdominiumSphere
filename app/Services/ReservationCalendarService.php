<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\CommonArea;
use App\Models\CommonAreaBlock;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;

class ReservationCalendarService
{
    /** @param array{from: string, to: string, area?: ?int} $filters */
    public function data(User $user, array $filters): array
    {
        $admin = $user->role === UserRole::Admin;
        $areaId = $filters['area'] ?? null;
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->addDay()->startOfDay();
        $areas = CommonArea::query()->when(! $admin, fn ($query) => $query->whereIn('status', ['active', 'maintenance']))
            ->orderBy('name')->get(['id', 'name', 'status', 'available_from', 'available_until', 'max_reservation_minutes', 'rules', 'description', 'requires_approval']);
        if (! $admin && $areaId && ! $areas->contains('id', (int) $areaId)) {
            abort(404);
        }

        $reservations = Reservation::query()->with($admin ? ['commonArea:id,name', 'user:id,name', 'unit:id,block,number'] : ['commonArea:id,name'])
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)
            ->when($areaId, fn ($query) => $query->where('common_area_id', $areaId))
            ->when(! $admin, fn ($query) => $query->whereIn('status', ['pending', 'confirmed']))
            ->orderBy('starts_at')->orderBy('id')->get()->map(function (Reservation $reservation) use ($admin, $user): array {
                $own = $reservation->user_id === $user->id;

                return [
                    'id' => $admin || $own ? $reservation->id : null,
                    'area_id' => $reservation->common_area_id,
                    'area' => $reservation->commonArea->name,
                    'start' => $reservation->starts_at->format('Y-m-d H:i:s'),
                    'end' => $reservation->ends_at->format('Y-m-d H:i:s'),
                    'status' => $admin || $own ? $reservation->status->value : null,
                    'status_label' => $admin || $own ? $reservation->status->label() : 'Ocupado',
                    ...($admin ? ['resident' => $reservation->user->name, 'unit' => $reservation->unit?->number] : []),
                ];
            });
        $blocks = CommonAreaBlock::query()->withExists('maintenanceRequest')->where('starts_at', '<', $to)->where('ends_at', '>', $from)
            ->when($areaId, fn ($query) => $query->where('common_area_id', $areaId))->get()
            ->map(fn (CommonAreaBlock $block): array => [
                'area_id' => $block->common_area_id,
                'start' => $block->starts_at->format('Y-m-d H:i:s'),
                'end' => $block->ends_at->format('Y-m-d H:i:s'),
                'maintenance' => (bool) $block->maintenance_request_exists,
            ]);

        return ['areas' => $areas, 'reservations' => $reservations, 'blocks' => $blocks, 'now' => now()->format('Y-m-d H:i:s')];
    }
}
