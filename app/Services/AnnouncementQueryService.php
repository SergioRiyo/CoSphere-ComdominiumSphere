<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class AnnouncementQueryService
{
    public function paginate(User $user): LengthAwarePaginator
    {
        return $this->visibleTo($user)->orderByRaw('published_at IS NULL')->orderByDesc('published_at')->orderByDesc('id')->paginate(15);
    }

    public function find(User $user, int $id): Announcement
    {
        return $this->visibleTo($user)->findOrFail($id);
    }

    private function visibleTo(User $user): Builder
    {
        Gate::forUser($user)->authorize('viewAny', Announcement::class);
        $query = Announcement::query()->with('author:id,name');
        if ($user->role === UserRole::Morador) {
            $query->whereNotNull('published_at');
        }

        return $query;
    }
}
