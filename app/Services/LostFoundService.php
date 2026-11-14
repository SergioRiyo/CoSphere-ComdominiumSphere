<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\LostFoundConversation;
use App\Models\LostFoundItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseTransactionRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class LostFoundService
{
    private const DISK = 'local';

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function index(User $user, array $filters): array
    {
        $this->authorizeUser($user);
        $query = LostFoundItem::query()->when($user->role === UserRole::Admin, fn ($query) => $query->withCount('conversations'))->when(
            ($filters['status'] ?? '') === 'resolved',
            fn ($query) => $query->where('status', 'resolved'),
            fn ($query) => $query->whereIn('status', ['open', 'in_progress']),
        )->when(! empty($filters['type']), fn ($query) => $query->where('type', $filters['type']));

        return [
            'items' => $query->latest('id')->paginate(15)->appends($filters)->through(fn (LostFoundItem $item) => $this->serialize($item, $user)),
            'filters' => $filters,
        ];
    }

    /** @return array<string, mixed> */
    public function detail(User $user, LostFoundItem $item): array
    {
        $this->authorizeUser($user);
        $admin = $user->role === UserRole::Admin;
        if ($admin) {
            $item->load('author.unit:id,block,number');
        }
        $data = $this->serialize($item, $user);
        if ($admin) {
            $data['author'] = ['name' => $item->author->name, 'unit' => $this->unitLabel($item->author)];
        }
        if ($admin || $item->author_id === $user->id) {
            $item->load('history.actor:id,name');
            $data['history'] = $item->history->map(fn ($event): array => [
                'action' => $event->action,
                'actor' => $admin ? $event->actor->name : ($event->actor_id === $user->id ? 'Você' : 'Administração'),
                'before' => $event->before, 'after' => $event->after,
                'created_at' => $event->created_at->format('d/m/Y H:i'),
            ]);
        }
        $threads = $item->conversations()->when(! $admin, fn ($query) => $query->where('resident_id', $user->id))
            ->with(['resident.unit:id,block,number', 'messages.sender:id,name,role'])->orderByDesc('id')->get();
        $data['conversations'] = $threads->map(fn (LostFoundConversation $thread): array => [
            'id' => $thread->id,
            ...($admin ? ['resident' => $thread->resident->name, 'unit' => $this->unitLabel($thread->resident)] : []),
            'messages' => $thread->messages->map(fn ($message): array => [
                'id' => $message->id, 'body' => $message->body,
                'sender' => $admin ? $message->sender->name : ($message->sender_id === $user->id ? 'Você' : 'Administração'),
                'created_at' => $message->created_at->format('d/m/Y H:i'),
            ]),
        ]);

        return $data;
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): LostFoundItem
    {
        $this->authorizeUser($user);
        abort_unless($user->role === UserRole::Morador, 403);
        $newPath = null;
        try {
            $item = DB::transaction(function () use ($user, $data, &$newPath): LostFoundItem {
                $item = new LostFoundItem(collect($data)->only(['type', 'description', 'condition', 'location', 'event_date'])->all());
                $item->author_id = $user->id;
                if (($data['photo'] ?? null) instanceof UploadedFile) {
                    [$newPath, $mime] = $this->storePhoto($data['photo']);
                    $item->photo_path = $newPath;
                    $item->photo_mime = $mime;
                }
                $item->save();
                $this->record($item, $user, 'created', null);

                return $item;
            });
            if ($newPath) {
                $this->registerRollbackCleanup($newPath);
            }

            return $item;
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk(self::DISK)->delete($newPath);
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, LostFoundItem $item, array $data): void
    {
        $this->authorizeUser($user);
        $newPath = null;
        try {
            DB::transaction(function () use ($user, $item, $data, &$newPath): void {
                $item = LostFoundItem::query()->lockForUpdate()->findOrFail($item->id);
                abort_unless($user->role === UserRole::Admin || ($user->role === UserRole::Morador && $item->author_id === $user->id && $item->status === 'open'), 403);
                $before = $this->snapshot($item);
                $oldPath = $item->photo_path;
                $item->fill(collect($data)->only(['type', 'description', 'condition', 'location', 'event_date'])->all());
                if (($data['photo'] ?? null) instanceof UploadedFile) {
                    [$newPath, $mime] = $this->storePhoto($data['photo']);
                    $item->photo_path = $newPath;
                    $item->photo_mime = $mime;
                } elseif ($data['remove_photo'] ?? false) {
                    $item->photo_path = null;
                    $item->photo_mime = null;
                }
                $item->save();
                $this->record($item, $user, 'updated', $before);
                if ($oldPath && $oldPath !== $item->photo_path) {
                    DB::afterCommit(fn () => Storage::disk(self::DISK)->delete($oldPath));
                }
            });
            if ($newPath) {
                $this->registerRollbackCleanup($newPath);
            }
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk(self::DISK)->delete($newPath);
            }
            throw $exception;
        }
    }

    public function changeStatus(User $user, LostFoundItem $item, string $status): void
    {
        $this->authorizeUser($user);
        DB::transaction(function () use ($user, $item, $status): void {
            $item = LostFoundItem::query()->lockForUpdate()->findOrFail($item->id);
            $admin = $user->role === UserRole::Admin;
            abort_unless($admin || ($user->role === UserRole::Morador && $item->author_id === $user->id && $status === 'resolved'), 403);
            if ($item->status === $status || (! $admin && $item->status === 'resolved')) {
                throw ValidationException::withMessages(['status' => 'A alteração não é permitida para o estado atual.']);
            }
            $before = $this->snapshot($item);
            $item->update(['status' => $status]);
            $this->record($item, $user, 'status_changed', $before);
        });
    }

    public function startConversation(User $user, LostFoundItem $item, string $body): void
    {
        $this->authorizeUser($user);
        abort_unless($user->role === UserRole::Morador, 403);
        DB::transaction(function () use ($user, $item, $body): void {
            $current = LostFoundItem::query()->lockForUpdate()->findOrFail($item->id);
            if ($current->status === 'resolved') {
                throw ValidationException::withMessages(['body' => 'Este item já foi finalizado.']);
            }
            $thread = LostFoundConversation::query()->firstOrCreate(['lost_found_item_id' => $current->id, 'resident_id' => $user->id]);
            $thread->messages()->create(['sender_id' => $user->id, 'body' => trim($body)]);
        });
    }

    public function reply(User $user, LostFoundItem $item, LostFoundConversation $thread, string $body): void
    {
        $this->authorizeUser($user);
        abort_unless($thread->lost_found_item_id === $item->id, 404);
        abort_unless($user->role === UserRole::Admin || ($user->role === UserRole::Morador && $thread->resident_id === $user->id), 403);
        DB::transaction(function () use ($user, $item, $thread, $body): void {
            $current = LostFoundItem::query()->lockForUpdate()->findOrFail($item->id);
            if ($current->status === 'resolved') {
                throw ValidationException::withMessages(['body' => 'Este item já foi finalizado.']);
            }
            $thread->messages()->create(['sender_id' => $user->id, 'body' => trim($body)]);
        });
    }

    public function authorizeUser(User $user): void
    {
        if (! $user->is_active || ! $user->hasVerifiedEmail() || ! in_array($user->role, [UserRole::Admin, UserRole::Morador], true)) {
            throw new AuthorizationException;
        }
    }

    /** @return array{string, string} */
    private function storePhoto(UploadedFile $photo): array
    {
        $mime = $photo->getMimeType();
        $extension = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'
        };
        $path = 'lost-found/'.Str::uuid().'.'.$extension;
        if (! Storage::disk(self::DISK)->putFileAs('lost-found', $photo, basename($path), ['visibility' => 'private'])) {
            throw new RuntimeException('Não foi possível salvar a foto.');
        }

        return [$path, $mime];
    }

    private function registerRollbackCleanup(string $path): void
    {
        $connection = DB::connection()->getName();
        app('db.transactions')->getPendingTransactions()
            ->filter(fn (DatabaseTransactionRecord $transaction): bool => $transaction->connection === $connection)
            ->each(fn (DatabaseTransactionRecord $transaction) => $transaction->addCallbackForRollback(
                static fn () => Storage::disk(self::DISK)->delete($path),
            ));
    }

    /** @return array<string, mixed> */
    private function serialize(LostFoundItem $item, User $user): array
    {
        return ['id' => $item->id, 'type' => $item->type, 'description' => $item->description,
            'condition' => $item->condition, 'location' => $item->location, 'event_date' => $item->event_date->toDateString(),
            'status' => $item->status, 'has_photo' => $item->photo_path !== null,
            'is_author' => $item->author_id === $user->id,
            ...($user->role === UserRole::Admin && $item->conversations_count !== null ? ['conversations_count' => $item->conversations_count] : [])];
    }

    /** @return array<string, mixed> */
    private function snapshot(LostFoundItem $item): array
    {
        return ['type' => $item->type, 'description' => $item->description, 'condition' => $item->condition,
            'location' => $item->location, 'event_date' => $item->event_date->toDateString(),
            'status' => $item->status, 'has_photo' => $item->photo_path !== null];
    }

    private function record(LostFoundItem $item, User $user, string $action, ?array $before): void
    {
        $item->history()->create(['actor_id' => $user->id, 'action' => $action, 'before' => $before, 'after' => $this->snapshot($item)]);
    }

    private function unitLabel(User $user): ?string
    {
        return $user->unit ? trim(($user->unit->block ? 'Bloco '.$user->unit->block.' · ' : '').'Unidade '.$user->unit->number) : null;
    }
}
