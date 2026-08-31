<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VisitorAccess;

class VisitorAccessPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActiveDoorman($user);
    }

    public function create(User $user): bool
    {
        return $this->isActiveDoorman($user);
    }

    public function update(User $user, VisitorAccess $visitorAccess): bool
    {
        return $this->isActiveDoorman($user);
    }

    private function isActiveDoorman(User $user): bool
    {
        return $user->role === UserRole::Porteiro && $user->is_active;
    }
}
