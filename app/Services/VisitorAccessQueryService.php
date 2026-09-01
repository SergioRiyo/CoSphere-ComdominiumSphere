<?php

namespace App\Services;

use App\Enums\VisitorAccessStatus;
use App\Models\Unit;
use App\Models\VisitorAccess;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class VisitorAccessQueryService
{
    /**
     * @param  array{search?: string, unit_id?: int, situation?: string, date_from?: string, date_to?: string}  $filters
     */
    public function paginateHistoryForPortaria(array $filters): LengthAwarePaginator
    {
        $query = VisitorAccess::query()
            ->select([
                'id',
                'visitor_authorization_id',
                'doorman_id',
                'exit_doorman_id',
                'entry_time',
                'exit_time',
                'validation_status',
                'created_at',
            ])
            ->with([
                'visitorAuthorization:id,visitor_id,unit_id,vehicle_plate',
                'visitorAuthorization.visitor:id,name',
                'visitorAuthorization.unit:id,block,number',
                'doorman:id,name',
                'exitDoorman:id,name',
            ]);

        $this->applyHistoryFilters($query, $filters);

        return $query
            ->orderByRaw('COALESCE(entry_time, created_at) DESC')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (VisitorAccess $access): array => $this->historySummary($access));
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    public function unitOptions(): Collection
    {
        return Unit::query()
            ->select(['id', 'block', 'number'])
            ->orderBy('block')
            ->orderBy('number')
            ->get()
            ->map(static fn (Unit $unit): array => [
                'id' => $unit->id,
                'label' => $unit->block === null
                    ? "Unidade {$unit->number}"
                    : "Bloco {$unit->block} · Unidade {$unit->number}",
            ]);
    }

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

    /**
     * @param  Builder<VisitorAccess>  $query
     * @param  array{search?: string, unit_id?: int, situation?: string, date_from?: string, date_to?: string}  $filters
     */
    private function applyHistoryFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $search = trim(str_replace(['%', '_'], '', $search));

                if ($search === '') {
                    $query->whereKey([]);

                    return;
                }

                $query->whereHas('visitorAuthorization.visitor', function (Builder $query) use ($search): void {
                    $query->whereLike('name', '%'.$search.'%');
                });
            })
            ->when($filters['unit_id'] ?? null, function (Builder $query, int $unitId): void {
                $query->whereHas('visitorAuthorization', function (Builder $query) use ($unitId): void {
                    $query->where('unit_id', $unitId);
                });
            })
            ->when($filters['situation'] ?? null, function (Builder $query, string $situation): void {
                $this->applySituationFilter($query, $situation);
            })
            ->when($filters['date_from'] ?? null, function (Builder $query, string $date): void {
                $this->applyDateFromFilter($query, CarbonImmutable::parse($date)->startOfDay());
            })
            ->when($filters['date_to'] ?? null, function (Builder $query, string $date): void {
                $this->applyDateToFilter($query, CarbonImmutable::parse($date)->endOfDay());
            });
    }

    /** @param Builder<VisitorAccess> $query */
    private function applySituationFilter(Builder $query, string $situation): void
    {
        match ($situation) {
            'present' => $query
                ->where('validation_status', VisitorAccessStatus::Validated)
                ->whereNotNull('entry_time')
                ->whereNull('exit_time'),
            'finished' => $query
                ->where('validation_status', VisitorAccessStatus::Validated)
                ->whereNotNull('entry_time')
                ->whereNotNull('exit_time'),
            'denied' => $query->where('validation_status', VisitorAccessStatus::Rejected),
            'validated' => $query
                ->where('validation_status', VisitorAccessStatus::Validated)
                ->whereNull('entry_time'),
            default => $query->where('validation_status', VisitorAccessStatus::Pending),
        };
    }

    /** @param Builder<VisitorAccess> $query */
    private function applyDateFromFilter(Builder $query, CarbonImmutable $date): void
    {
        $query->where(function (Builder $query) use ($date): void {
            $query->where('entry_time', '>=', $date)
                ->orWhere(function (Builder $query) use ($date): void {
                    $query->whereNull('entry_time')
                        ->where('created_at', '>=', $date);
                });
        });
    }

    /** @param Builder<VisitorAccess> $query */
    private function applyDateToFilter(Builder $query, CarbonImmutable $date): void
    {
        $query->where(function (Builder $query) use ($date): void {
            $query->where('entry_time', '<=', $date)
                ->orWhere(function (Builder $query) use ($date): void {
                    $query->whereNull('entry_time')
                        ->where('created_at', '<=', $date);
                });
        });
    }

    /**
     * @return array{visitor_name: string, unit: array{block: string|null, number: string}, vehicle_plate: string|null, entry_time: string|null, exit_time: string|null, entry_doorman_name: string|null, exit_doorman_name: string|null, situation: string, situation_label: string}
     */
    private function historySummary(VisitorAccess $access): array
    {
        $authorization = $access->visitorAuthorization;
        [$situation, $situationLabel] = $this->situation($access);

        return [
            'visitor_name' => $authorization?->visitor?->name ?? 'Visitante não identificado',
            'unit' => [
                'block' => $authorization?->unit?->block,
                'number' => $authorization?->unit?->number ?? '—',
            ],
            'vehicle_plate' => $authorization?->vehicle_plate,
            'entry_time' => $access->entry_time?->toIso8601String(),
            'exit_time' => $access->exit_time?->toIso8601String(),
            'entry_doorman_name' => $access->doorman?->name,
            'exit_doorman_name' => $access->exitDoorman?->name,
            'situation' => $situation,
            'situation_label' => $situationLabel,
        ];
    }

    /** @return array{0: string, 1: string} */
    private function situation(VisitorAccess $access): array
    {
        if ($access->validation_status === VisitorAccessStatus::Rejected) {
            return ['denied', 'Negado'];
        }

        if ($access->validation_status === VisitorAccessStatus::Validated) {
            if ($access->entry_time === null) {
                return ['validated', VisitorAccessStatus::Validated->label()];
            }

            return $access->exit_time === null
                ? ['present', 'Presente']
                : ['finished', 'Finalizado'];
        }

        return [$access->validation_status->value, $access->validation_status->label()];
    }
}
