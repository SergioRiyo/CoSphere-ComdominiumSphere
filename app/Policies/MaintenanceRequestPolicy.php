<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\MaintenanceRequest;
use App\Models\User;

class MaintenanceRequestPolicy
{
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
