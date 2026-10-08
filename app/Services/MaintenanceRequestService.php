<?php

namespace App\Services;

use App\Enums\MaintenanceRequestStatus;
use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestStatusHistory;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MaintenanceRequestService
{
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
                'service_provider_id' => null, 'admin_id' => null,
                'scheduled_at' => null, 'executed_at' => null, 'cost' => null,
            ]);
            if (! $request->save()) {
                throw new RuntimeException('Não foi possível persistir a manutenção vinculada.');
            }
            $this->recordHistory($request, $actor, null);

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
    public function transition(User $actor, MaintenanceRequest $request, MaintenanceRequestStatus $target, array $data = [], ?string $reason = null): MaintenanceRequest
    {
        return DB::transaction(function () use ($actor, $request, $target, $data, $reason): MaintenanceRequest {
            $current = MaintenanceRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            $actor = $this->resolveActor($actor);
            Gate::forUser($actor)->authorize('transition', $current);
            if (! $current->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'Transição de manutenção inválida. Atualize os dados.']);
            }
            $reason = Validator::make(['reason' => $reason], ['reason' => ['nullable', 'string', 'max:255']])->validate()['reason'];
            $changes = ['status' => $target, 'admin_id' => $actor->id];
            if ($target === MaintenanceRequestStatus::Scheduled) {
                $schedule = Validator::make($data, [
                    'scheduled_at' => ['required', 'date', 'after_or_equal:'.$current->created_at->toDateTimeString()],
                    'service_provider_id' => ['required', 'integer'],
                ])->validate();
                $changes += $schedule;
            }
            if (in_array($target, [MaintenanceRequestStatus::Scheduled, MaintenanceRequestStatus::InProgress, MaintenanceRequestStatus::Completed], true)) {
                $providerId = $changes['service_provider_id'] ?? $current->service_provider_id;
                if ($providerId === null || ServiceProvider::query()->lockForUpdate()->find($providerId) === null) {
                    throw ValidationException::withMessages(['service_provider_id' => 'Atribua um prestador válido para executar a manutenção.']);
                }
            }
            if (in_array($target, [MaintenanceRequestStatus::InProgress, MaintenanceRequestStatus::Completed], true)
                && ($current->scheduled_at === null || $current->scheduled_at->isFuture())) {
                throw ValidationException::withMessages(['scheduled_at' => 'A execução exige um agendamento já iniciado.']);
            }
            if ($target === MaintenanceRequestStatus::Completed) {
                $execution = Validator::make($data, [
                    'cost' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
                ])->validate();
                $changes += $execution + ['executed_at' => now()];
            }
            $from = $current->status;
            if (! $current->update($changes)) {
                throw new RuntimeException('Não foi possível persistir o status da manutenção.');
            }
            $this->recordHistory($current, $actor, $from, $reason);

            return $current;
        });
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
