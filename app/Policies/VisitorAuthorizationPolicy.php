<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VisitorAuthorization;

class VisitorAuthorizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Morador;
    }

    public function view(User $user, VisitorAuthorization $authorization): bool
    {
        return $this->viewAny($user)
            && $user->unit_id !== null
            && $user->unit_id === $authorization->unit_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user)
            && $user->is_active
            && $user->unit()->where('status', 'active')->exists();
    }

    public function cancel(User $user, VisitorAuthorization $authorization): bool
    {
        return $this->view($user, $authorization)
            && $user->is_active
            && $user->id === $authorization->resident_id
            && $user->unit()->where('status', 'active')->exists();
    }
}
