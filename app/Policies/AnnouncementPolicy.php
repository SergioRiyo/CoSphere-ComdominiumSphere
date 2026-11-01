<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->exists && $user->is_active && $user->hasVerifiedEmail()
            && in_array($user->role, [UserRole::Admin, UserRole::Morador], true);
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $this->viewAny($user) && ! $announcement->trashed()
            && ($user->role === UserRole::Admin || $announcement->published_at !== null);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->role === UserRole::Admin;
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $this->create($user) && ! $announcement->trashed() && $announcement->published_at === null;
    }

    public function publish(User $user, Announcement $announcement): bool
    {
        return $this->create($user) && ! $announcement->trashed();
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $this->update($user, $announcement);
    }

    public function forceDelete(User $user, Announcement $announcement): bool
    {
        return false;
    }
}
