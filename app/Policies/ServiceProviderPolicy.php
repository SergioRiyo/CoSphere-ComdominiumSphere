<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ServiceProvider;
use App\Models\User;

class ServiceProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->exists && $user->is_active && $user->hasVerifiedEmail() && $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, ServiceProvider $provider): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ServiceProvider $provider): bool
    {
        return $this->viewAny($user) && ! $provider->trashed();
    }

    public function archive(User $user, ServiceProvider $provider): bool
    {
        return $this->update($user, $provider);
    }
}
