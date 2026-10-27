<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\MaintenanceRequest;
use App\Models\User;

class MaintenanceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->exists && $user->is_active && $user->hasVerifiedEmail() && $user->role === UserRole::Admin;
    }

    public function createAdministrative(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, MaintenanceRequest $request): bool
    {
        return $this->transition($user, $request);
    }

    public function create(User $user): bool
    {
        return $user->exists && $user->is_active && $user->hasVerifiedEmail()
            && $user->role === UserRole::Morador
            && $user->unit()->where('status', 'active')->exists();
    }

    public function view(User $user, MaintenanceRequest $request): bool
    {
        return $user->exists && $user->is_active && $user->hasVerifiedEmail()
            && ($user->role === UserRole::Admin
                || ($user->role === UserRole::Morador && $user->id === $request->resident_id));
    }

    public function transition(User $user, MaintenanceRequest $request): bool
    {
        return ! $request->trashed() && $this->view($user, $request) && $user->role === UserRole::Admin;
    }
}
