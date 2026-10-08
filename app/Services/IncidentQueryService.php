<?php

namespace App\Services;

use App\Enums\IncidentCategory;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\IncidentAttachment;
use App\Models\IncidentPriorityHistory;
use App\Models\IncidentStatusHistory;
use App\Models\MaintenanceRequest;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class IncidentQueryService
{
    /** @return Builder<Incident> */
    private function accessible(User $user): Builder
    {
        Gate::forUser($user)->authorize('viewAny', Incident::class);

        return Incident::query()->when($user->role === UserRole::Morador,
            fn (Builder $query) => $query->where('resident_id', $user->id));
    }

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        $query = $this->accessible($user)
            ->select(['id', 'resident_id', 'unit_id', 'title', 'type', 'category', 'status', 'priority', 'opened_at', 'created_at'])
            ->withCount(['attachments', 'maintenanceRequests']);
        if ($user->role === UserRole::Admin) {
            $query->with(['resident:id,name', 'unit:id,block,number,complement']);
        }
        $fields = ['type', 'category', 'status'];
        if ($user->role === UserRole::Admin) {
            $fields = [...$fields, 'priority', 'resident_id', 'unit_id'];
        }
        foreach ($fields as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', CarbonImmutable::parse($filters['date_from'])->startOfDay());
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<', CarbonImmutable::parse($filters['date_to'])->addDay()->startOfDay());
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->appends($filters)
            ->through(fn (Incident $incident): array => $this->serialize($incident, $user));
    }

    /** @return array<string, mixed> */
    public function details(User $user, int $id): array
    {
        $incident = $this->accessible($user)->findOrFail($id);
        Gate::forUser($user)->authorize('view', $incident);
        $incident->load(['attachments', 'statusHistory', 'priorityHistory', 'maintenanceRequests.serviceProvider:id,name']);
        if ($user->role === UserRole::Admin) {
            $incident->load(['resident:id,name', 'unit:id,block,number,complement', 'statusHistory.changedBy:id,name', 'priorityHistory.changedBy:id,name']);
        }
        $history = $incident->statusHistory->map(fn (IncidentStatusHistory $event): array => [
            'key' => 'status-'.$event->id, 'kind' => 'status', 'id' => $event->id,
            'from_label' => $event->from_status?->label(), 'to_label' => $event->to_status->label(),
            'actor' => $this->actorLabel($event, $user), 'reason' => $event->reason,
            'created_at' => $event->created_at->format('Y-m-d H:i:s'),
        ])->concat($incident->priorityHistory->map(fn (IncidentPriorityHistory $event): array => [
            'key' => 'priority-'.$event->id, 'kind' => 'priority', 'id' => $event->id,
            'from_label' => $event->from_priority->label(), 'to_label' => $event->to_priority->label(),
            'actor' => $this->actorLabel($event, $user), 'reason' => null,
            'created_at' => $event->created_at->format('Y-m-d H:i:s'),
        ]))->sortBy([
            ['created_at', 'asc'],
            fn (array $a, array $b): int => ($a['kind'] === 'status' ? 0 : 1) <=> ($b['kind'] === 'status' ? 0 : 1),
            ['id', 'asc'],
        ])->values()->all();

        return $this->serialize($incident, $user) + [
            'description' => $incident->description,
            'history' => $history,
            'attachments' => $incident->attachments->map(fn (IncidentAttachment $attachment): array => [
                'id' => $attachment->id, 'name' => $attachment->original_name, 'size' => $attachment->size,
            ])->all(),
            'maintenance_requests' => $incident->maintenanceRequests->map(fn (MaintenanceRequest $maintenance): array => [
                'id' => $maintenance->id, 'status' => $maintenance->status->value,
                'status_label' => $maintenance->status->label(),
                'provider' => $maintenance->serviceProvider?->name,
                'created_at' => $maintenance->created_at->format('Y-m-d H:i:s'),
                'scheduled_at' => $maintenance->scheduled_at?->format('Y-m-d H:i:s'),
                'executed_at' => $maintenance->executed_at?->format('Y-m-d H:i:s'),
            ])->all(),
            'allowed_statuses' => $user->role === UserRole::Admin
                ? array_values(array_filter($this->enumOptions(IncidentStatus::cases()),
                    fn (array $option): bool => $incident->status->canTransitionTo(IncidentStatus::from($option['value'])))) : [],
            'can_update_priority' => Gate::forUser($user)->allows('updatePriority', $incident),
            'can_create_maintenance' => Gate::forUser($user)->allows('createMaintenance', $incident)
                && ! $incident->maintenanceRequests()->withTrashed()->exists(),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    public function categories(User $user): array
    {
        $options = $this->enumOptions(IncidentCategory::cases());
        $legacy = $this->accessible($user)->whereNotNull('category')->distinct()->toBase()->pluck('category');
        foreach ($legacy as $category) {
            if (IncidentCategory::tryFrom($category) === null) {
                $options[] = ['value' => $category, 'label' => $category.' (legada)'];
            }
        }

        return $options;
    }

    /** @return array<string, mixed> */
    public function options(User $user): array
    {
        Gate::forUser($user)->authorize('viewAny', Incident::class);

        return [
            'types' => $this->enumOptions(IncidentType::cases()),
            'categories' => $this->categories($user),
            'statuses' => $this->enumOptions(IncidentStatus::cases()),
            'priorities' => $this->enumOptions(IncidentPriority::cases()),
            ...($user->role === UserRole::Admin ? [
                'residents' => User::query()->whereHas('reportedIncidents')->orderBy('name')->orderBy('id')->get(['id', 'name'])
                    ->map(fn (User $resident): array => ['value' => (string) $resident->id, 'label' => $resident->name])->all(),
                'units' => Unit::query()->whereHas('incidents')->orderBy('block')->orderBy('number')->get(['id', 'block', 'number', 'complement'])
                    ->map(fn (Unit $unit): array => ['value' => (string) $unit->id, 'label' => $this->unitLabel($unit)])->all(),
            ] : []),
        ];
    }

    /** @param list<IncidentType|IncidentCategory|IncidentStatus|IncidentPriority> $cases
     * @return list<array{value: string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(fn (IncidentType|IncidentCategory|IncidentStatus|IncidentPriority $case): array => ['value' => $case->value, 'label' => $case->label()], $cases);
    }

    private function actorLabel(IncidentStatusHistory|IncidentPriorityHistory $event, User $user): string
    {
        return $user->role === UserRole::Admin
            ? $event->changedBy->name.' ('.$event->actor_role->label().')'
            : ($event->changed_by_user_id === $user->id ? 'Você' : $event->actor_role->label());
    }

    private function unitLabel(Unit $unit): string
    {
        return implode(' · ', array_filter([
            $unit->block ? 'Bloco '.$unit->block : null, 'Unidade '.$unit->number, $unit->complement,
        ]));
    }

    /** @return array<string, mixed> */
    private function serialize(Incident $incident, User $user): array
    {
        return [
            'id' => $incident->id, 'title' => $incident->title,
            'type' => $incident->type->value, 'type_label' => $incident->type->label(),
            'category' => $incident->getRawOriginal('category'),
            'category_label' => $incident->category instanceof IncidentCategory ? $incident->category->label() : ($incident->category ?? 'Sem categoria'),
            'status' => $incident->status->value, 'status_label' => $incident->status->label(),
            'priority' => $incident->priority->value, 'priority_label' => $incident->priority->label(),
            'opened_at' => $incident->opened_at->format('Y-m-d H:i:s'),
            'created_at' => $incident->created_at->format('Y-m-d H:i:s'),
            'attachments_count' => $incident->attachments_count ?? $incident->attachments->count(),
            'maintenance_count' => $incident->maintenance_requests_count ?? $incident->maintenanceRequests->count(),
            ...($user->role === UserRole::Admin ? [
                'resident' => $incident->resident->name, 'unit' => $this->unitLabel($incident->unit),
            ] : []),
        ];
    }
}
