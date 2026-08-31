<?php

namespace App\Services;

use App\Enums\VisitorAccessStatus;
use App\Models\VisitorAccess;
use Illuminate\Support\Collection;

class VisitorAccessQueryService
{
    /**
     * @return Collection<int, array{
     *     id: int,
     *     visitor_name: string,
     *     unit: array{block: string|null, number: string},
     *     vehicle_plate: string|null,
     *     entry_time: string,
     *     entry_doorman_name: string|null
     * }>
     */
    public function openForPortaria(): Collection
    {
        return VisitorAccess::query()
            ->select([
                'id',
                'visitor_authorization_id',
                'doorman_id',
                'entry_time',
            ])
            ->where('validation_status', VisitorAccessStatus::Validated)
            ->whereNotNull('entry_time')
            ->whereNull('exit_time')
            ->with([
                'visitorAuthorization:id,visitor_id,unit_id,vehicle_plate',
                'visitorAuthorization.visitor:id,name',
                'visitorAuthorization.unit:id,block,number',
                'doorman:id,name',
            ])
            ->orderByDesc('entry_time')
            ->orderByDesc('id')
            ->get()
            ->map(static function (VisitorAccess $access): array {
                $authorization = $access->visitorAuthorization;

                return [
                    'id' => $access->id,
                    'visitor_name' => $authorization?->visitor?->name
                        ?? 'Visitante não identificado',
                    'unit' => [
                        'block' => $authorization?->unit?->block,
                        'number' => $authorization?->unit?->number ?? '—',
                    ],
                    'vehicle_plate' => $authorization?->vehicle_plate,
                    'entry_time' => $access->entry_time->toIso8601String(),
                    'entry_doorman_name' => $access->doorman?->name,
                ];
            });
    }
}
