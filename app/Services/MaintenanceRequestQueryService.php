<?php

namespace App\Services;

use App\Enums\MaintenanceRequestStatus;
use App\Enums\UserRole;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestChange;
use App\Models\MaintenanceRequestStatusHistory;
use App\Models\ServiceProvider;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class MaintenanceRequestQueryService
{
    /** @param array<string, mixed> $filters */
    public function paginate(User $actor, array $filters): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', MaintenanceRequest::class);
        $query = MaintenanceRequest::query()->with(['unit:id,block,number,complement', 'incident:id,title', 'admin:id,name', 'serviceProvider:id,name,deleted_at']);
        foreach (['unit_id', 'admin_id', 'service_provider_id', 'status'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        if (($filters['link'] ?? '') === 'linked') {
            $query->whereNotNull('incident_id');
        }
        if (($filters['link'] ?? '') === 'direct') {
            $query->whereNull('incident_id');
        }
        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', CarbonImmutable::parse($filters['date_from'])->startOfDay());
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<', CarbonImmutable::parse($filters['date_to'])->addDay()->startOfDay());
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->appends($filters)
            ->through(fn (MaintenanceRequest $item): array => $this->serialize($item, true));
    }

    /** @return array<string, mixed> */
    public function options(User $actor, bool $filters = false): array
    {
        Gate::forUser($actor)->authorize('viewAny', MaintenanceRequest::class);

        return [
            'units' => Unit::query()->when(! $filters, fn (Builder $query): Builder => $query->where('status', 'active'))->orderBy('block')->orderBy('number')->get(['id', 'block', 'number', 'complement'])
                ->map(fn (Unit $unit): array => ['value' => (string) $unit->id, 'label' => $this->unitLabel($unit)])->all(),
            'admins' => User::query()->where('role', UserRole::Admin)->when(! $filters, fn (Builder $query): Builder => $query->where('is_active', true))->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $user): array => ['value' => (string) $user->id, 'label' => $user->name])->all(),
            'providers' => ServiceProvider::query()->when($filters, fn (Builder $query): Builder => $query->withTrashed())->orderBy('name')->get(['id', 'name', 'deleted_at'])
                ->map(fn (ServiceProvider $provider): array => ['value' => (string) $provider->id, 'label' => $provider->name.($provider->trashed() ? ' (arquivado)' : '')])->all(),
            'statuses' => array_map(fn (MaintenanceRequestStatus $status): array => ['value' => $status->value, 'label' => $status->label()], MaintenanceRequestStatus::cases()),
        ];
    }

    /** @return array<string, mixed> */
    public function details(User $actor, MaintenanceRequest $request): array
    {
        Gate::forUser($actor)->authorize('viewAny', MaintenanceRequest::class);
        Gate::forUser($actor)->authorize('view', $request);
        $request->load(['unit:id,block,number,complement', 'incident:id,title', 'admin:id,name', 'serviceProvider:id,name,deleted_at', 'statusHistory.changedBy:id,name', 'changes.changedBy:id,name']);

        return $this->serialize($request) + [
            'history' => $this->history($request, true),
            'can_edit' => ! in_array($request->status, [MaintenanceRequestStatus::Completed, MaintenanceRequestStatus::Canceled], true),
            'allowed_statuses' => array_values(array_map(fn (MaintenanceRequestStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                array_filter(MaintenanceRequestStatus::cases(), fn (MaintenanceRequestStatus $status): bool => $request->status->canTransitionTo($status)))),
        ];
    }

    /** Relations are eager loaded by the authorized occurrence query.
     * @return array<string, mixed>
     */
    public function residentSummary(MaintenanceRequest $request): array
    {
        return ['id' => $request->id, 'description' => $request->description, 'status' => $request->status->value, 'status_label' => $request->status->label(),
            'provider' => $request->serviceProvider?->name,
            'created_at' => $request->created_at->format('Y-m-d H:i:s'),
            'scheduled_at' => $request->scheduled_at?->format('Y-m-d H:i:s'), 'executed_at' => $request->executed_at?->format('Y-m-d H:i:s'),
            'history' => $this->history($request, false)];
    }

    /** @return list<array<string, mixed>> */
    private function history(MaintenanceRequest $request, bool $admin): array
    {
        $status = $request->statusHistory->map(fn (MaintenanceRequestStatusHistory $event): array => [
            'key' => 'status-'.$event->id, 'kind' => 'status', 'at' => $event->created_at->format('Y-m-d H:i:s'),
            'actor' => $admin ? $event->changedBy->name.' ('.$event->actor_role->label().')' : $event->actor_role->label(),
            'text' => ($event->from_status?->label() ?? 'Registro').' → '.$event->to_status->label(),
            'reason' => $admin ? $event->reason : null, 'changes' => [],
        ]);
        $changes = $request->changes->map(function (MaintenanceRequestChange $event) use ($admin): array {
            $fields = $admin ? $event->changes : array_intersect_key($event->changes, array_flip(['description', 'service_provider_id', 'scheduled_at']));
            if (! $admin && isset($fields['service_provider_id'])) {
                $fields['service_provider_id'] = ['old' => $fields['service_provider_id']['old_label'] ?? null, 'new' => $fields['service_provider_id']['new_label'] ?? null];
            }

            return ['key' => 'change-'.$event->id, 'kind' => 'change', 'at' => $event->created_at->format('Y-m-d H:i:s'),
                'actor' => $admin ? $event->changedBy->name.' ('.$event->actor_role->label().')' : $event->actor_role->label(),
                'text' => 'Informações atualizadas', 'reason' => null, 'changes' => $fields];
        })->filter(fn (array $event): bool => $event['changes'] !== []);

        return $status->concat($changes)->sortBy('at')->values()->all();
    }

    private function unitLabel(Unit $unit): string
    {
        return implode(' · ', array_filter([$unit->block, $unit->number, $unit->complement]));
    }

    /** @return array<string, mixed> */
    private function serialize(MaintenanceRequest $request, bool $summary = false): array
    {
        return ['id' => $request->id, 'unit_id' => $request->unit_id, 'unit' => $this->unitLabel($request->unit),
            'incident_id' => $request->incident_id, 'incident_title' => $request->incident?->title,
            'admin_id' => $request->admin_id, 'admin' => $request->admin?->name,
            'service_provider_id' => $request->service_provider_id, 'provider' => $request->serviceProvider?->name,
            'provider_archived' => $request->serviceProvider?->trashed() ?? false,
            'description' => $summary ? Str::limit($request->description, 160) : $request->description,
            'status' => $request->status->value, 'status_label' => $request->status->label(),
            'scheduled_at' => $request->scheduled_at?->format('Y-m-d H:i:s'), 'executed_at' => $request->executed_at?->format('Y-m-d H:i:s'),
            'created_at' => $request->created_at->format('Y-m-d H:i:s'), 'cost' => $request->cost];
    }
}
