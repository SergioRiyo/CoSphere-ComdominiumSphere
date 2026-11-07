<?php

namespace App\Services;

use App\Enums\VisitorAccessStatus;
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
            'accesses' => $this->accesses($filters),
            'options' => $this->options(),
        ];
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

    /** @return array<string, list<array{value: string, label: string}>> */
    private function options(): array
    {
        return [
            'access_situations' => [
                ['value' => 'pending', 'label' => 'Aguardando (legado)'],
                ['value' => 'validated', 'label' => 'Validado sem entrada (legado)'],
                ['value' => 'present', 'label' => 'Presente'],
                ['value' => 'finished', 'label' => 'Finalizado'],
                ['value' => 'denied', 'label' => 'Entrada negada'],
            ],
        ];
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
