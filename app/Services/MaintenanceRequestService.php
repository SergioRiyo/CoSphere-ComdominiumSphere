<?php

namespace App\Services;

use App\Enums\MaintenanceRequestStatus;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestChange;
use App\Models\MaintenanceRequestStatusHistory;
use App\Models\ServiceProvider;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MaintenanceRequestService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function createFromIncident(User $actor, Incident $incident): MaintenanceRequest
    {
        return DB::transaction(function () use ($actor, $incident): MaintenanceRequest {
            $current = Incident::query()->lockForUpdate()->findOrFail($incident->getKey());
            $actor = $this->resolveActor($actor);
            Gate::forUser($actor)->authorize('createMaintenance', $current);
            if ($current->maintenanceRequests()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['maintenance' => 'Esta solicitação já possui manutenção vinculada.']);
            }
            $request = new MaintenanceRequest([
                'incident_id' => $current->id, 'resident_id' => $current->resident_id,
                'unit_id' => $current->unit_id, 'description' => $current->description,
                'status' => MaintenanceRequestStatus::Pending,
                'service_provider_id' => null, 'admin_id' => $actor->id,
                'scheduled_at' => null, 'executed_at' => null, 'cost' => null,
            ]);
            if (! $request->save()) {
                throw new RuntimeException('Não foi possível persistir a manutenção vinculada.');
            }
            $this->recordHistory($request, $actor, null);
            $this->notify($request, 'Manutenção vinculada registrada.');

            return $request;
        });
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): MaintenanceRequest
    {
        return DB::transaction(function () use ($actor, $data): MaintenanceRequest {
            $validated = Validator::make($data, [
                'description' => ['required', 'string', 'max:10000'],
                'incident_id' => ['nullable', 'integer'],
            ])->validate();
            $incident = isset($validated['incident_id'])
                ? Incident::query()->lockForUpdate()->findOrFail($validated['incident_id']) : null;
            $actor = $this->resolveActor($actor);
            Gate::forUser($actor)->authorize('create', MaintenanceRequest::class);
            if ($incident !== null) {
                Gate::forUser($actor)->authorize('view', $incident);
                if ($incident->resident_id !== $actor->id || $incident->unit_id !== $actor->unit_id) {
                    throw new AuthorizationException('A ocorrência não pertence ao responsável e à unidade atuais.');
                }
            }
            $request = MaintenanceRequest::create([
                'incident_id' => $incident?->id,
                'resident_id' => $actor->id, 'unit_id' => $actor->unit_id,
                'description' => $validated['description'],
                'status' => MaintenanceRequestStatus::Pending,
                'service_provider_id' => null, 'admin_id' => null,
                'scheduled_at' => null, 'executed_at' => null, 'cost' => null,
            ]);
            $this->recordHistory($request, $actor, null);

            return $request;
        });
    }

    /** @param array<string, mixed> $data */
    public function createAdministrative(User $actor, array $data): MaintenanceRequest
    {
        return DB::transaction(function () use ($actor, $data): MaintenanceRequest {
            $actor = $this->resolveActor($actor);
            Gate::forUser($actor)->authorize('createAdministrative', MaintenanceRequest::class);
            $unitId = Validator::make($data, ['unit_id' => ['required', 'integer']])->validate()['unit_id'];
            if (! Unit::query()->whereKey($unitId)->where('status', 'active')->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['unit_id' => 'Selecione uma unidade ativa.']);
            }
            $request = new MaintenanceRequest(['unit_id' => $unitId, 'resident_id' => null, 'incident_id' => null, 'admin_id' => $actor->id]);
            $request->created_at = now()->startOfSecond();
            $this->fillAdministrative($request, $data, MaintenanceRequestStatus::Pending, true);
            if (! $request->save()) {
                throw new RuntimeException('Não foi possível salvar a manutenção.');
            }
            $this->recordHistory($request, $actor, null);

            return $request;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function transition(User $actor, MaintenanceRequest $request, MaintenanceRequestStatus $target, array $data = [], ?string $reason = null): MaintenanceRequest
    {
        return $this->update($actor, $request, $data, $target, $reason);
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, MaintenanceRequest $request, array $data, ?MaintenanceRequestStatus $target = null, ?string $reason = null): MaintenanceRequest
    {
        return DB::transaction(function () use ($actor, $request, $data, $target, $reason): MaintenanceRequest {
            $current = MaintenanceRequest::query()->lockForUpdate()->findOrFail($request->id);
            $actor = $this->resolveActor($actor);
            Gate::forUser($actor)->authorize('update', $current);
            if (in_array($current->status, [MaintenanceRequestStatus::Completed, MaintenanceRequestStatus::Canceled], true)
                || ($target !== null && ! $current->status->canTransitionTo($target))) {
                throw ValidationException::withMessages(['status' => 'Transição ou alteração inválida. A manutenção pode estar encerrada.']);
            }
            $reason = Validator::make(['reason' => $reason], ['reason' => ['nullable', 'string', 'max:255']])->validate()['reason'];
            $from = $current->status;
            $before = $this->snapshot($current);
            // Legacy pending records can receive their first responsible administrator here.
            if ($current->admin_id === null && ! array_key_exists('admin_id', $data)) {
                $data['admin_id'] = $actor->id;
            }
            $this->fillAdministrative($current, $data, $target ?? $from);
            if ($target !== null) {
                $current->status = $target;
            }
            if ($target === MaintenanceRequestStatus::Completed) {
                $current->executed_at = now();
            }
            $after = $this->snapshot($current);
            $changes = [];
            foreach ($after as $field => $value) {
                if ($before[$field] !== $value) {
                    $changes[$field] = ['old' => $before[$field], 'new' => $value];
                    if (in_array($field, ['admin_id', 'service_provider_id'], true)) {
                        $model = $field === 'admin_id' ? User::query() : ServiceProvider::withTrashed();
                        $names = $model->whereIn('id', array_filter([$before[$field], $value]))->pluck('name', 'id');
                        $changes[$field] += ['old_label' => $names[$before[$field]] ?? null, 'new_label' => $names[$value] ?? null];
                    }
                }
            }
            if (! $current->isDirty()) {
                return $current;
            }
            if (! $current->save()) {
                throw new RuntimeException('Não foi possível persistir o status da manutenção.');
            }
            if ($target !== null) {
                $this->recordHistory($current, $actor, $from, $reason);
            }
            if ($changes !== []) {
                $history = new MaintenanceRequestChange(['changes' => $changes, 'actor_role' => $actor->role]);
                $history->maintenanceRequest()->associate($current);
                $history->changedBy()->associate($actor);
                if (! $history->save()) {
                    throw new RuntimeException('Não foi possível persistir as alterações da manutenção.');
                }
            }
            if ($target !== null || array_intersect(array_keys($changes), ['description', 'service_provider_id', 'scheduled_at']) !== []) {
                $this->notify($current, $target ? 'Novo status: '.$target->label().'.' : 'Informações da manutenção atualizadas.');
            }

            return $current;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function fillAdministrative(MaintenanceRequest $request, array $data, MaintenanceRequestStatus $status, bool $creating = false): void
    {
        $validated = Validator::make($data, [
            'description' => [$creating ? 'required' : 'sometimes', 'required', 'string', 'max:10000'],
            'admin_id' => ['sometimes', 'required', 'integer'],
            'service_provider_id' => ['sometimes', 'nullable', 'integer'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'cost' => ['sometimes', 'nullable', 'regex:/^[0-9]{1,8}(?:\\.[0-9]{1,2})?$/D'],
        ], ['cost.regex' => 'Informe um custo não negativo, até 99.999.999,99 e com no máximo duas casas decimais.'])->validate();
        foreach (['admin_id', 'service_provider_id'] as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = (int) $validated[$field];
            }
        }
        $previousProvider = $request->service_provider_id;
        $request->fill($validated);
        $responsible = User::query()->whereKey($request->admin_id)->lockForUpdate()->first();
        if (! $responsible || $responsible->role !== UserRole::Admin || ! $responsible->is_active) {
            throw ValidationException::withMessages(['admin_id' => 'Selecione um administrador ativo.']);
        }
        if ($request->service_provider_id !== null) {
            $provider = ServiceProvider::withTrashed()->whereKey($request->service_provider_id)->lockForUpdate()->first();
            if (! $provider || ($provider->trashed() && ($creating || $previousProvider !== $request->service_provider_id))) {
                throw ValidationException::withMessages(['service_provider_id' => 'Selecione um prestador ativo para uma nova atribuição.']);
            }
        }
        if ($request->scheduled_at !== null && $request->scheduled_at->lt($request->created_at)) {
            throw ValidationException::withMessages(['scheduled_at' => 'O agendamento não pode ser anterior ao cadastro.']);
        }
        if (in_array($status, [MaintenanceRequestStatus::Scheduled, MaintenanceRequestStatus::InProgress, MaintenanceRequestStatus::Completed], true)
            && $request->service_provider_id === null) {
            throw ValidationException::withMessages(['service_provider_id' => 'Atribua um prestador para agendar ou executar a manutenção.']);
        }
        if ($status === MaintenanceRequestStatus::Scheduled && $request->scheduled_at === null) {
            throw ValidationException::withMessages(['scheduled_at' => 'Informe a data do agendamento.']);
        }
        if (in_array($status, [MaintenanceRequestStatus::InProgress, MaintenanceRequestStatus::Completed], true)
            && $request->scheduled_at?->isFuture()) {
            throw ValidationException::withMessages(['scheduled_at' => 'A execução exige que o agendamento já tenha iniciado.']);
        }
        if ($status === MaintenanceRequestStatus::Completed && $request->created_at->isFuture()) {
            throw ValidationException::withMessages(['executed_at' => 'A execução não pode preceder o cadastro.']);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(MaintenanceRequest $request): array
    {
        return ['description' => $request->description, 'admin_id' => $request->admin_id,
            'service_provider_id' => $request->service_provider_id,
            'scheduled_at' => $request->scheduled_at?->format('Y-m-d H:i:s'), 'cost' => $request->cost];
    }

    private function notify(MaintenanceRequest $request, string $event): void
    {
        $incident = $request->incident;
        if ($incident === null) {
            return;
        }
        $message = $incident->title.' — Manutenção #'.$request->id.'. '.$event;
        if ($request->scheduled_at !== null) {
            $message .= ' Agendamento: '.$request->scheduled_at->format('d/m/Y H:i').'.';
        }
        $notification = $this->notifications->create($incident->resident_id, 'Atualização de manutenção', $message, NotificationType::Occurrence);
        if (! $notification->exists) {
            throw new RuntimeException('Não foi possível persistir a notificação da manutenção.');
        }
    }

    private function resolveActor(User $actor): User
    {
        $current = $actor->exists ? User::query()->lockForUpdate()->find($actor->getKey()) : null;
        if ($current === null) {
            throw new AuthorizationException('Usuário inválido.');
        }

        return $current;
    }

    private function recordHistory(MaintenanceRequest $request, User $actor, ?MaintenanceRequestStatus $from, ?string $reason = null): void
    {
        $history = new MaintenanceRequestStatusHistory([
            'from_status' => $from, 'to_status' => $request->status,
            'actor_role' => $actor->role, 'reason' => $reason,
        ]);
        $history->maintenanceRequest()->associate($request);
        $history->changedBy()->associate($actor);
        if (! $history->save()) {
            throw new RuntimeException('Não foi possível persistir o histórico da manutenção.');
        }
    }
}
