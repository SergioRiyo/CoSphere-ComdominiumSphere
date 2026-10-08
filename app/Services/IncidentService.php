<?php

namespace App\Services;

use App\Enums\IncidentCategory;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\IncidentPriorityHistory;
use App\Models\IncidentStatusHistory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use RuntimeException;

class IncidentService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly IncidentAttachmentService $attachments,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $attachments
     */
    public function create(User $actor, array $data, array $attachments = []): Incident
    {
        return DB::transaction(function () use ($actor, $data, $attachments): Incident {
            $actor = $this->resolveActor($actor);
            Gate::forUser($actor)->authorize('create', Incident::class);
            $validated = Validator::make($data, [
                'title' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string', 'max:10000'],
                'category' => ['required', Rule::enum(IncidentCategory::class)],
                'type' => ['sometimes', 'required', Rule::enum(IncidentType::class)],
            ])->validate();
            $incident = Incident::create([
                ...$validated,
                'resident_id' => $actor->id,
                'unit_id' => $actor->unit_id,
                'opened_at' => now(),
                'status' => IncidentStatus::Open,
                'priority' => IncidentPriority::Medium,
            ]);
            $this->recordHistory($incident, $actor, null);
            foreach ($attachments as $index => $file) {
                try {
                    $this->attachments->store($actor, $incident, $file);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['attachments.'.$index => collect($exception->errors())->flatten()->all()]);
                } catch (FilesystemException|RuntimeException $exception) {
                    report($exception);
                    throw ValidationException::withMessages(['attachments.'.$index => 'Não foi possível enviar o arquivo. Tente novamente.']);
                }
            }
            $message = $incident->type->label().': '.$incident->title.' — '.$incident->category->label().'. Status: '.$incident->status->label().'.';
            $this->notify($incident->resident_id, 'Solicitação registrada', $message);
            User::query()->where('role', UserRole::Admin)->where('is_active', true)->orderBy('id')->pluck('id')
                ->each(fn (int $id) => $this->notify($id, 'Nova solicitação', $message));

            return $incident;
        });
    }

    public function transition(User $actor, Incident $incident, IncidentStatus $target, ?string $reason = null): Incident
    {
        return DB::transaction(function () use ($actor, $incident, $target, $reason): Incident {
            $current = Incident::query()->lockForUpdate()->findOrFail($incident->getKey());
            $actor = $this->resolveActor($actor);
            Gate::forUser($actor)->authorize('transition', $current);
            if (! $current->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'Transição de ocorrência inválida. Atualize os dados.']);
            }
            $reason = Validator::make(['reason' => $reason], ['reason' => ['nullable', 'string', 'max:255']])->validate()['reason'];
            $from = $current->status;
            if (! $current->update(['status' => $target])) {
                throw new RuntimeException('Não foi possível persistir o status da ocorrência.');
            }
            $this->recordHistory($current, $actor, $from, $reason);
            $this->notify($current->resident_id, 'Status da solicitação atualizado', $current->title.'. Novo status: '.$current->status->label().'.');

            return $current;
        });
    }

    public function updatePriority(User $actor, Incident $incident, IncidentPriority $priority): Incident
    {
        return DB::transaction(function () use ($actor, $incident, $priority): Incident {
            $current = Incident::query()->lockForUpdate()->findOrFail($incident->getKey());
            $actor = $this->resolveActor($actor);
            Gate::forUser($actor)->authorize('updatePriority', $current);
            if ($current->priority === $priority) {
                return $current;
            }
            $from = $current->priority;
            if (! $current->update(['priority' => $priority])) {
                throw new RuntimeException('Não foi possível persistir a prioridade da ocorrência.');
            }
            $history = new IncidentPriorityHistory([
                'from_priority' => $from, 'to_priority' => $priority, 'actor_role' => $actor->role,
            ]);
            $history->incident()->associate($current);
            $history->changedBy()->associate($actor);
            if (! $history->save()) {
                throw new RuntimeException('Não foi possível persistir o histórico de prioridade.');
            }
            $this->notify($current->resident_id, 'Prioridade da solicitação atualizada', $current->title.'. Nova prioridade: '.$priority->label().'.');

            return $current;
        });
    }

    private function notify(int $recipientId, string $title, string $message): void
    {
        $notification = $this->notifications->create($recipientId, $title, $message, NotificationType::Occurrence);
        if (! $notification->exists) {
            throw new RuntimeException('Não foi possível persistir a notificação da ocorrência.');
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

    private function recordHistory(Incident $incident, User $actor, ?IncidentStatus $from, ?string $reason = null): void
    {
        $history = new IncidentStatusHistory([
            'from_status' => $from, 'to_status' => $incident->status,
            'actor_role' => $actor->role, 'reason' => $reason,
        ]);
        $history->incident()->associate($incident);
        $history->changedBy()->associate($actor);
        if (! $history->save()) {
            throw new RuntimeException('Não foi possível persistir o histórico da ocorrência.');
        }
    }
}
