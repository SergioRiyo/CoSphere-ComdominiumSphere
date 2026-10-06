<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\User;

class IncidentPolicy
{
    public function create(User $user): bool
    {
        return $this->eligible($user) && $user->role === UserRole::Morador
            && $user->unit()->where('status', 'active')->exists();
    }

    public function view(User $user, Incident $incident): bool
    {
        return $this->eligible($user) && ($user->role === UserRole::Admin
            || ($user->role === UserRole::Morador && $user->id === $incident->resident_id));
    }

    public function uploadAttachment(User $user, Incident $incident): bool
    {
        return ! $incident->trashed() && $this->view($user, $incident);
    }

    public function transition(User $user, Incident $incident): bool
    {
        return ! $incident->trashed() && $this->eligible($user) && $user->role === UserRole::Admin;
    }

    public function updatePriority(User $user, Incident $incident): bool
    {
        return $this->transition($user, $incident);
    }

    private function eligible(User $user): bool
    {
        return $user->exists && $user->is_active && $user->hasVerifiedEmail();
    }
}
