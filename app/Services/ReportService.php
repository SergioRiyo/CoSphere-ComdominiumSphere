<?php

namespace App\Services;

use App\Enums\IncidentCategory;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\ReservationStatus;
use App\Enums\VisitorAccessStatus;
use App\Models\CommonArea;
use App\Models\Incident;
use App\Models\Reservation;
use App\Models\Unit;
use App\Models\VisitorAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ReportService
{
    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function data(array $filters): array
    {
        return [
            'reservations' => $this->reservations($filters),
            'accesses' => $this->accesses($filters),
            'incidents' => $this->incidents($filters),
            'options' => $this->options(),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function reservations(array $filters): LengthAwarePaginator
    {
        $query = Reservation::query()
            ->with(['commonArea:id,name', 'user:id,name', 'unit:id,block,number,complement'])
            ->when($filters['reservation_status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['common_area_id'] ?? null, fn (Builder $query, int $areaId): Builder => $query->where('common_area_id', $areaId));

        $this->applyPeriod($query, 'starts_at', $filters['reservation_date_from'] ?? null, $filters['reservation_date_to'] ?? null);

        return $query->orderByDesc('starts_at')->orderByDesc('id')
            ->paginate(10, ['*'], 'reservations_page')->appends($filters)
            ->through(fn (Reservation $reservation): array => [
                'area' => $reservation->commonArea->name,
                'resident' => $reservation->user->name,
                'unit' => $this->unitLabel($reservation->unit),
                'starts_at' => $reservation->starts_at->toIso8601String(),
                'ends_at' => $reservation->ends_at->toIso8601String(),
                'status' => $reservation->status->value,
                'status_label' => $reservation->status->label(),
            ]);
    }

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function accesses(array $filters): LengthAwarePaginator
    {
        $query = VisitorAccess::query()
            ->select(['id', 'visitor_authorization_id', 'doorman_id', 'exit_doorman_id', 'entry_time', 'exit_time', 'validation_status', 'created_at'])
            ->with([
                'visitorAuthorization:id,visitor_id,unit_id',
                'visitorAuthorization.visitor:id,name',
                'visitorAuthorization.unit:id,block,number,complement',
                'doorman:id,name',
                'exitDoorman:id,name',
            ]);

        if (isset($filters['access_situation'])) {
            $this->applyAccessSituation($query, $filters['access_situation']);
        }
        $this->applyAccessPeriod($query, $filters['access_date_from'] ?? null, $filters['access_date_to'] ?? null);

        return $query->orderByRaw('COALESCE(entry_time, created_at) DESC')->orderByDesc('id')
            ->paginate(10, ['*'], 'accesses_page')->appends($filters)
            ->through(function (VisitorAccess $access): array {
                [$situation, $label] = $this->accessSituation($access);

                return [
                    'visitor' => $access->visitorAuthorization?->visitor?->name ?? 'Visitante não identificado',
                    'unit' => $this->unitLabel($access->visitorAuthorization?->unit),
                    'entry_at' => $access->entry_time?->toIso8601String(),
                    'exit_at' => $access->exit_time?->toIso8601String(),
                    'entry_doorman' => $access->doorman?->name,
                    'exit_doorman' => $access->exitDoorman?->name,
                    'situation' => $situation,
                    'situation_label' => $label,
                ];
            });
    }

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function incidents(array $filters): LengthAwarePaginator
    {
        $query = Incident::query()
            ->where('type', IncidentType::Incident)
            ->with('unit:id,block,number,complement')
            ->when($filters['incident_category'] ?? null, fn (Builder $query, string $category): Builder => $query->where('category', $category))
            ->when($filters['incident_status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status));

        $this->applyPeriod($query, 'opened_at', $filters['incident_date_from'] ?? null, $filters['incident_date_to'] ?? null);

        return $query->orderByDesc('opened_at')->orderByDesc('id')
            ->paginate(10, ['*'], 'incidents_page')->appends($filters)
            ->through(function (Incident $incident): array {
                $category = $incident->category;

                return [
                    'category' => $category instanceof IncidentCategory ? $category->value : $category,
                    'category_label' => $category instanceof IncidentCategory ? $category->label() : $category,
                    'title' => $incident->title,
                    'unit' => $this->unitLabel($incident->unit),
                    'status' => $incident->status->value,
                    'status_label' => $incident->status->label(),
                    'opened_at' => $incident->opened_at->toIso8601String(),
                    'updated_at' => $incident->updated_at->toIso8601String(),
                ];
            });
    }

    /** @return array<string, list<array{value: string, label: string}>> */
    private function options(): array
    {
        return [
            'common_areas' => CommonArea::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (CommonArea $area): array => ['value' => (string) $area->id, 'label' => $area->name])->all(),
            'reservation_statuses' => array_map(fn (ReservationStatus $status): array => ['value' => $status->value, 'label' => $status->label()], ReservationStatus::cases()),
            'access_situations' => [
                ['value' => 'pending', 'label' => 'Aguardando (legado)'],
                ['value' => 'validated', 'label' => 'Validado sem entrada (legado)'],
                ['value' => 'present', 'label' => 'Presente'],
                ['value' => 'finished', 'label' => 'Finalizado'],
                ['value' => 'denied', 'label' => 'Entrada negada'],
            ],
            'incident_categories' => array_map(fn (IncidentCategory $category): array => ['value' => $category->value, 'label' => $category->label()], IncidentCategory::cases()),
            'incident_statuses' => array_map(fn (IncidentStatus $status): array => ['value' => $status->value, 'label' => $status->label()], IncidentStatus::cases()),
        ];
    }

    /** @param Builder<Reservation|Incident> $query */
    private function applyPeriod(Builder $query, string $column, ?string $from, ?string $to): void
    {
        if ($from !== null) {
            $query->where($column, '>=', CarbonImmutable::parse($from)->startOfDay());
        }
        if ($to !== null) {
            $query->where($column, '<', CarbonImmutable::parse($to)->addDay()->startOfDay());
        }
    }

    /** @param Builder<VisitorAccess> $query */
    private function applyAccessSituation(Builder $query, string $situation): void
    {
        match ($situation) {
            'present' => $query->where('validation_status', VisitorAccessStatus::Validated)->whereNotNull('entry_time')->whereNull('exit_time'),
            'finished' => $query->where('validation_status', VisitorAccessStatus::Validated)->whereNotNull('entry_time')->whereNotNull('exit_time'),
            'denied' => $query->where('validation_status', VisitorAccessStatus::Rejected),
            'validated' => $query->where('validation_status', VisitorAccessStatus::Validated)->whereNull('entry_time'),
            default => $query->where('validation_status', VisitorAccessStatus::Pending),
        };
    }

    /** @param Builder<VisitorAccess> $query */
    private function applyAccessPeriod(Builder $query, ?string $from, ?string $to): void
    {
        if ($from !== null) {
            $date = CarbonImmutable::parse($from)->startOfDay();
            $query->where(fn (Builder $query): Builder => $query->where('entry_time', '>=', $date)
                ->orWhere(fn (Builder $query): Builder => $query->whereNull('entry_time')->where('created_at', '>=', $date)));
        }
        if ($to !== null) {
            $date = CarbonImmutable::parse($to)->addDay()->startOfDay();
            $query->where(fn (Builder $query): Builder => $query->where('entry_time', '<', $date)
                ->orWhere(fn (Builder $query): Builder => $query->whereNull('entry_time')->where('created_at', '<', $date)));
        }
    }

    /** @return array{0: string, 1: string} */
    private function accessSituation(VisitorAccess $access): array
    {
        if ($access->validation_status === VisitorAccessStatus::Rejected) {
            return ['denied', 'Entrada negada'];
        }
        if ($access->validation_status === VisitorAccessStatus::Validated) {
            if ($access->entry_time === null) {
                return ['validated', 'Validado sem entrada (legado)'];
            }

            return $access->exit_time === null ? ['present', 'Presente'] : ['finished', 'Finalizado'];
        }

        return ['pending', 'Aguardando (legado)'];
    }

    private function unitLabel(?Unit $unit): string
    {
        if ($unit === null) {
            return 'Unidade não identificada';
        }

        return implode(' · ', array_filter([$unit->block, $unit->number, $unit->complement]));
    }
}
