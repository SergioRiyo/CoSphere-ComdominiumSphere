<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class AnnouncementService
{
    public function __construct(private NotificationService $notifications) {}

    /** @param array<string, mixed> $data */
    public function save(User $actor, array $data, ?Announcement $announcement = null): Announcement
    {
        return DB::transaction(function () use ($actor, $data, $announcement): Announcement {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $current = $announcement ? Announcement::query()->lockForUpdate()->findOrFail($announcement->id) : new Announcement;
            Gate::forUser($actor)->authorize($announcement ? 'update' : 'create', $announcement ? $current : Announcement::class);
            $validated = Validator::make($data, ['title' => ['required', 'string', 'max:255'], 'content' => ['required', 'string']])->validate();
            $current->fill($validated);
            if (! $current->exists) {
                $current->author()->associate($actor);
            }
            $current->saveOrFail();

            return $current;
        });
    }

    public function publish(User $actor, Announcement $announcement): Announcement
    {
        return DB::transaction(function () use ($actor, $announcement): Announcement {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $current = Announcement::query()->lockForUpdate()->findOrFail($announcement->id);
            Gate::forUser($actor)->authorize('publish', $current);
            if ($current->published_at !== null) {
                return $current;
            }

            $current->published_at = now();
            $current->saveOrFail();
            User::query()->where('role', UserRole::Morador->value)->where('is_active', true)
                ->whereNotNull('email_verified_at')->orderBy('id')->chunkById(100, function ($residents) use ($current): void {
                    foreach ($residents as $resident) {
                        $this->notifications->createForAnnouncement($current->id, $resident->id, $current->title, $current->content);
                    }
                });

            return $current;
        });
    }
}
