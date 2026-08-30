<?php

namespace App\Services;

use App\Enums\VisitorAuthorizationStatus;
use App\Models\User;
use App\Models\VisitorAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class VisitorAuthorizationQueryService
{
    public function __construct(private VisitorQrCodeService $visitorQrCodeService) {}

    /**
     * @param  array{search?: string, status?: string, date_from?: string, date_to?: string}  $filters
     */
    public function paginateForResident(User $resident, array $filters): LengthAwarePaginator
    {
        $query = VisitorAuthorization::query()
            ->select([
                'id',
                'visitor_id',
                'unit_id',
                'vehicle_plate',
                'start_date',
                'end_date',
                'status',
                'invitation_expires_at',
            ])
            ->with([
                'visitor:id,name,cpf',
                'unit:id,block,number',
            ]);

        if ($resident->unit_id === null) {
            $query->whereNull('visitor_authorizations.id');
        } else {
            $query->where('unit_id', $resident->unit_id);
        }

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (VisitorAuthorization $authorization): array => $this->summary($authorization));
    }

    /**
     * @return array<string, mixed>
     */
    public function details(VisitorAuthorization $authorization): array
    {
        $authorization->loadMissing([
            'visitor:id,name,cpf,phone',
            'resident:id,unit_id,role,is_active',
            'unit:id,block,number,status',
        ]);
        $status = $this->effectiveStatus($authorization);

        return [
            'id' => $authorization->id,
            'visitor' => $authorization->visitor === null ? null : [
                'name' => $authorization->visitor->name,
                'cpf' => $authorization->visitor->cpf,
                'phone' => $authorization->visitor->phone,
            ],
            'unit' => [
                'block' => $authorization->unit->block,
                'number' => $authorization->unit->number,
            ],
            'vehicle_plate' => $authorization->vehicle_plate,
            'start_date' => $authorization->start_date->toIso8601String(),
            'end_date' => $authorization->end_date->toIso8601String(),
            'status' => $status->value,
            'status_label' => $status->label(),
            'qr_available' => $this->visitorQrCodeService->isAvailable($authorization),
        ];
    }

    /**
     * @param  array{search?: string, status?: string, date_from?: string, date_to?: string}  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $search = trim(str_replace(['%', '_'], '', $search));

                if ($search === '') {
                    $query->whereKey([]);

                    return;
                }

                $pattern = '%'.$search.'%';
                $cpfPattern = $this->cpfPattern($search);

                $query->whereHas('visitor', function (Builder $query) use ($cpfPattern, $pattern): void {
                    $query->whereLike('name', $pattern)
                        ->orWhereLike('cpf', $pattern)
                        ->when(
                            $cpfPattern !== null,
                            fn (Builder $query): Builder => $query->orWhereLike('cpf', $cpfPattern),
                        );
                });
            })
            ->when($filters['status'] ?? null, function (Builder $query, string $status): void {
                $this->applyStatusFilter($query, VisitorAuthorizationStatus::from($status));
            })
            ->when($filters['date_from'] ?? null, function (Builder $query, string $date): void {
                $query->where('end_date', '>=', CarbonImmutable::parse($date)->startOfDay());
            })
            ->when($filters['date_to'] ?? null, function (Builder $query, string $date): void {
                $query->where('start_date', '<=', CarbonImmutable::parse($date)->endOfDay());
            });
    }

    private function applyStatusFilter(Builder $query, VisitorAuthorizationStatus $status): void
    {
        if ($status === VisitorAuthorizationStatus::Expired) {
            $query->where(function (Builder $query): void {
                $query->where('status', VisitorAuthorizationStatus::Expired)
                    ->orWhere(function (Builder $query): void {
                        $query->where('status', VisitorAuthorizationStatus::Active)
                            ->where('end_date', '<', now());
                    })
                    ->orWhere(function (Builder $query): void {
                        $query->where('status', VisitorAuthorizationStatus::PendingData)
                            ->where(function (Builder $query): void {
                                $query->whereNull('invitation_expires_at')
                                    ->orWhere('invitation_expires_at', '<', now())
                                    ->orWhere('start_date', '<=', now());
                            });
                    });
            });

            return;
        }

        $query->where('status', $status);

        if ($status === VisitorAuthorizationStatus::Active) {
            $query->where('end_date', '>=', now());
        }

        if ($status === VisitorAuthorizationStatus::PendingData) {
            $query->whereNotNull('invitation_expires_at')
                ->where('invitation_expires_at', '>=', now())
                ->where('start_date', '>', now());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(VisitorAuthorization $authorization): array
    {
        $status = $this->effectiveStatus($authorization);

        return [
            'id' => $authorization->id,
            'visitor' => $authorization->visitor === null ? null : [
                'name' => $authorization->visitor->name,
                'cpf' => $this->maskedCpf($authorization->visitor->cpf),
            ],
            'unit' => [
                'block' => $authorization->unit->block,
                'number' => $authorization->unit->number,
            ],
            'start_date' => $authorization->start_date->toIso8601String(),
            'end_date' => $authorization->end_date->toIso8601String(),
            'status' => $status->value,
            'status_label' => $status->label(),
        ];
    }

    private function effectiveStatus(VisitorAuthorization $authorization): VisitorAuthorizationStatus
    {
        if ($authorization->status === VisitorAuthorizationStatus::Active
            && $authorization->end_date->isPast()) {
            return VisitorAuthorizationStatus::Expired;
        }

        if ($authorization->status === VisitorAuthorizationStatus::PendingData
            && ($authorization->invitation_expires_at === null
                || $authorization->invitation_expires_at->isPast()
                || $authorization->start_date->isPast())) {
            return VisitorAuthorizationStatus::Expired;
        }

        return $authorization->status;
    }

    private function cpfPattern(string $search): ?string
    {
        $digits = (string) preg_replace('/\D/', '', $search);

        if (strlen($digits) !== 11) {
            return null;
        }

        return sprintf(
            '%%%s.%s.%s-%s%%',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 3),
            substr($digits, 9, 2),
        );
    }

    private function maskedCpf(string $cpf): string
    {
        $digits = (string) preg_replace('/\D/', '', $cpf);

        return '***.***.***-'.substr($digits, -2);
    }
}
