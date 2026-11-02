<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Poll;
use App\Models\User;

class PollPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->exists && $user->is_active && $user->hasVerifiedEmail()
            && in_array($user->role, [UserRole::Admin, UserRole::Morador], true);
    }

    public function view(User $user, Poll $poll): bool
    {
        return $this->viewAny($user) && ($user->role === UserRole::Admin
            || ($poll->published_at !== null && ! $poll->published_at->isFuture()));
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->role === UserRole::Admin;
    }

    public function update(User $user, Poll $poll): bool
    {
        return $this->create($user) && $poll->published_at === null;
    }

    public function publish(User $user, Poll $poll): bool
    {
        return $this->create($user);
    }

    public function close(User $user, Poll $poll): bool
    {
        return $this->create($user);
    }

    public function vote(User $user, Poll $poll): bool
    {
        return $this->view($user, $poll) && $user->role === UserRole::Morador;
    }

    public function results(User $user, Poll $poll): bool
    {
        return $this->create($user);
    }
}
