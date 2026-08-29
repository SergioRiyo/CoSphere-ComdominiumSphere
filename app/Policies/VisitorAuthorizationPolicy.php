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
}
