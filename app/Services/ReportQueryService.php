<?php

namespace App\Services;

use App\Enums\IncidentCategory;
use App\Enums\IncidentStatus;
use App\Enums\MaintenanceRequestStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\CommonArea;
use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\Reservation;
use App\Models\ServiceProvider;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ReportQueryService
{
    public const EXPORT_BATCH_SIZE = 250;

    public function __construct(private readonly IncidentQueryService $incidents) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function report(User $user, string $kind, array $filters): array
    {
        $query = $this->filtered($user, $kind, $filters);
        $metrics = $this->metrics(clone $query, $kind);
        $records = $this->ordered($this->withDetails(clone $query, $kind), $this->dateColumn($kind, $filters))
            ->paginate(15)->appends($filters)->through(fn ($record): array => $this->row($record));

        return ['kind' => $kind, 'records' => $records, 'metrics' => $metrics,
            'filters' => Arr::except($filters, 'page'), 'options' => $this->options($user, $kind)];
    }

    /** Authorization happens before the streamed response is sent.
     * @param  array<string, mixed>  $filters
     * @return Builder<Reservation|Incident|MaintenanceRequest>
     */
    public function filtered(User $user, string $kind, array $filters): Builder
    {
        abort_unless($user->exists && $user->role === UserRole::Admin && $user->is_active && $user->hasVerifiedEmail(), 403);
        $query = match ($kind) {
            'reservations' => Reservation::query(),
            'incidents' => Incident::query(),
            'maintenances' => MaintenanceRequest::query(),
        };
        $fields = match ($kind) {
            'reservations' => ['common_area_id' => 'common_area_id', 'unit_id' => 'unit_id', 'resident_id' => 'user_id', 'status' => 'status'],
            'incidents' => ['unit_id' => 'unit_id', 'resident_id' => 'resident_id', 'status' => 'status', 'type' => 'type', 'category' => 'category', 'priority' => 'priority'],
            'maintenances' => ['status' => 'status', 'service_provider_id' => 'service_provider_id'],
        };
        foreach ($fields as $filter => $column) {
            if (isset($filters[$filter]) && $filters[$filter] !== '') {
                $query->where($column, $filters[$filter]);
            }
        }
        if ($kind === 'maintenances' && ! empty($filters['origin'])) {
            $filters['origin'] === 'direct' ? $query->whereNull('incident_id') : $query->whereNotNull('incident_id');
        }
        $date = $this->dateColumn($kind, $filters);
        if (! empty($filters['date_from'])) {
            $query->where($date, '>=', CarbonImmutable::parse($filters['date_from'])->startOfDay());
        }
        if (! empty($filters['date_to'])) {
            $query->where($date, '<', CarbonImmutable::parse($filters['date_to'])->addDay()->startOfDay());
        }

        return $query;
    }

    /** @param array<string, mixed> $filters */
    public function dateColumn(string $kind, array $filters): string
    {
        return match ($kind) {
            'reservations' => 'starts_at',
            'incidents' => 'created_at',
            'maintenances' => match ($filters['date_reference'] ?? 'opening') {
                'scheduled' => 'scheduled_at', 'executed' => 'executed_at', default => 'created_at',
            },
        };
    }

    /** Keyset batches preserve date DESC / id DESC, including optional null dates last.
     * Only one bounded batch (and its belongs-to relations) is buffered by PDO/Eloquent.
     *
     * @param  Builder<Reservation|Incident|MaintenanceRequest>  $query
     * @return Generator<int, array<string, int|string|null>>
     */
    public function exportRows(Builder $query, string $kind, string $date): Generator
    {
        $base = $this->ordered($this->withDetails(clone $query, $kind), $date);
        $lastId = null;
        $lastDate = null;
        do {
            $batchQuery = clone $base;
            if ($lastId !== null) {
                if ($lastDate === null) {
                    $batchQuery->whereNull($date)->where('id', '<', $lastId);
                } else {
                    $batchQuery->where(fn (Builder $q) => $q->where($date, '<', $lastDate)
                        ->orWhere(fn (Builder $q) => $q->where($date, $lastDate)->where('id', '<', $lastId))
                        ->orWhereNull($date));
                }
            }
            $batch = $batchQuery->limit(self::EXPORT_BATCH_SIZE)->get();
            foreach ($batch as $record) {
                yield $this->row($record, true);
            }
            if ($batch->isNotEmpty()) {
                $lastId = $batch->last()->id;
                $lastDate = $batch->last()->getRawOriginal($date);
            }
        } while ($batch->count() === self::EXPORT_BATCH_SIZE);
    }

    /** @param Builder<Reservation|Incident|MaintenanceRequest> $query
     * @return Builder<Reservation|Incident|MaintenanceRequest>
     */
    private function ordered(Builder $query, string $date): Builder
    {
        return $query->orderByRaw('CASE WHEN '.$date.' IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc($date)->orderByDesc('id');
    }

    /** @param Builder<Reservation|Incident|MaintenanceRequest> $query
     * @return Builder<Reservation|Incident|MaintenanceRequest>
     */
    private function withDetails(Builder $query, string $kind): Builder
    {
        return match ($kind) {
            'reservations' => $query->select(['id', 'common_area_id', 'user_id', 'unit_id', 'starts_at', 'ends_at', 'status'])
                ->with(['commonArea:id,name', 'user:id,name', 'unit:id,block,number,complement']),
            'incidents' => $query->select(['id', 'title', 'type', 'category', 'priority', 'status', 'resident_id', 'unit_id', 'created_at'])
                ->with(['resident:id,name', 'unit:id,block,number,complement']),
            'maintenances' => $query->select(['id', 'description', 'unit_id', 'incident_id', 'admin_id', 'service_provider_id', 'status', 'created_at', 'scheduled_at', 'executed_at', 'cost'])
                ->with(['unit:id,block,number,complement', 'incident:id,title', 'admin:id,name', 'serviceProvider:id,name']),
        };
    }

    /** @param Builder<Reservation|Incident|MaintenanceRequest> $query
     * @return array{total: int, statuses: list<array{value: string, label: string, count: int}>, cost_total?: string, cost_average?: ?string, cost_count?: int}
     */
    private function metrics(Builder $query, string $kind): array
    {
        $counts = (clone $query)->toBase()->select('status')->selectRaw('COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $statuses = match ($kind) {
            'reservations' => ReservationStatus::cases(), 'incidents' => IncidentStatus::cases(), 'maintenances' => MaintenanceRequestStatus::cases(),
        };
        $result = ['total' => (int) $counts->sum(), 'statuses' => array_map(fn ($status): array => [
            'value' => $status->value, 'label' => $status->label(), 'count' => (int) ($counts[$status->value] ?? 0),
        ], $statuses)];
        if ($kind === 'maintenances') {
            $costs = $query->toBase()->selectRaw('SUM(CAST(ROUND(cost * 100, 0) AS BIGINT)) AS cost_cents, COUNT(cost) AS cost_count')->first();
            $cents = (int) ($costs->cost_cents ?? 0);
            $count = (int) $costs->cost_count;
            $average = $count === 0 ? null : intdiv($cents, $count) + (int) (($cents % $count) >= intdiv($count, 2) + ($count % 2));
            $result += ['cost_total' => $this->decimal($cents), 'cost_average' => $average === null ? null : $this->decimal($average), 'cost_count' => $count];
        }

        return $result;
    }

    /** Costs are aggregated in integer cents on both databases; the mean excludes nulls,
     * includes zero, and rounds half-up to cents without PHP floating-point arithmetic.
     */
    private function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /** @return array<string, int|string|null> */
    private function row(Reservation|Incident|MaintenanceRequest $record, bool $export = false): array
    {
        $common = ['id' => $record->id, 'status' => $record->status->label(), 'unit' => $this->unitLabel($record->unit)];
        if ($record instanceof Reservation) {
            return $common + ['area' => $record->commonArea?->name, 'date' => $record->starts_at->format('d/m/Y'),
                'start' => $record->starts_at->format('H:i:s'), 'end' => $record->ends_at->format('H:i:s'), 'resident' => $record->user?->name];
        }
        if ($record instanceof Incident) {
            return $common + ['title' => $record->title, 'type' => $record->type->label(),
                'category' => $record->category instanceof IncidentCategory ? $record->category->label() : $record->category,
                'priority' => $record->priority->label(), 'resident' => $record->resident?->name,
                'created_at' => $record->created_at->format('d/m/Y H:i:s')];
        }

        return $common + ['description' => $export ? $record->description : Str::limit($record->description, 160),
            'origin' => $record->incident_id === null ? 'Direta' : 'Vinculada',
            'incident' => $record->incident ? '#'.$record->incident->id.' — '.$record->incident->title : null,
            'admin' => $record->admin?->name, 'provider' => $record->serviceProvider?->name,
            'created_at' => $record->created_at->format('d/m/Y H:i:s'),
            'scheduled_at' => $record->scheduled_at?->format('d/m/Y H:i:s'), 'executed_at' => $record->executed_at?->format('d/m/Y H:i:s'), 'cost' => $record->cost];
    }

    /** @return array<string, mixed> */
    private function options(User $user, string $kind): array
    {
        if ($kind === 'incidents') {
            return $this->incidents->options($user);
        }
        if ($kind === 'maintenances') {
            return [
                'statuses' => array_map(fn (MaintenanceRequestStatus $status): array => ['value' => $status->value, 'label' => $status->label()], MaintenanceRequestStatus::cases()),
                'providers' => ServiceProvider::withTrashed()->orderBy('name')->orderBy('id')->get(['id', 'name', 'deleted_at'])->map(fn (ServiceProvider $provider): array => ['value' => (string) $provider->id, 'label' => $provider->name.($provider->trashed() ? ' (arquivado)' : '')])->all(),
                'references' => [['value' => 'opening', 'label' => 'Abertura'], ['value' => 'scheduled', 'label' => 'Agendamento'], ['value' => 'executed', 'label' => 'Execução']],
                'origins' => [['value' => 'direct', 'label' => 'Direta'], ['value' => 'linked', 'label' => 'Vinculada']],
            ];
        }

        return [
            'statuses' => array_map(fn (ReservationStatus $status): array => ['value' => $status->value, 'label' => $status->label()], ReservationStatus::cases()),
            'areas' => CommonArea::query()->orderBy('name')->orderBy('id')->get(['id', 'name'])->map(fn (CommonArea $area): array => ['value' => (string) $area->id, 'label' => $area->name])->all(),
            'units' => Unit::query()->whereHas('reservations')->orderBy('block')->orderBy('number')->orderBy('id')->get(['id', 'block', 'number', 'complement'])->map(fn (Unit $unit): array => ['value' => (string) $unit->id, 'label' => $this->unitLabel($unit)])->all(),
            'residents' => User::query()->whereHas('reservations')->orderBy('name')->orderBy('id')->get(['id', 'name'])->map(fn (User $resident): array => ['value' => (string) $resident->id, 'label' => $resident->name])->all(),
        ];
    }

    private function unitLabel(?Unit $unit): ?string
    {
        return $unit === null ? null : implode(' · ', array_filter([$unit->block ? 'Bloco '.$unit->block : null, 'Unidade '.$unit->number, $unit->complement]));
    }
}
