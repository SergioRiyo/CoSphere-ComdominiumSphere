<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class PollQueryService
{
    public function paginate(User $user): LengthAwarePaginator
    {
        return $this->visibleTo($user)->orderByDesc('id')->paginate(15);
    }

    public function find(User $user, int $id): Poll
    {
        return $this->visibleTo($user)->with('options')->findOrFail($id);
    }

    public function hasVoted(User $user, Poll $poll): bool
    {
        $current = $this->visibleTo($user)->findOrFail($poll->id);
        Gate::forUser($user)->authorize('vote', $current);

        return $current->votes()->where('user_id', $user->id)->exists();
    }

    /** @return array{total: int, options: list<array{id: int, text: string, votes: int, percentage: float}>} */
    public function results(User $user, Poll $poll): array
    {
        Gate::forUser($user)->authorize('results', $poll);
        $options = $poll->options()->withCount('votes')->get();
        $total = (int) $options->sum('votes_count');

        return [
            'total' => $total,
            'options' => $options->map(fn (PollOption $option): array => [
                'id' => $option->id,
                'text' => $option->text,
                'votes' => (int) $option->votes_count,
                'percentage' => $total > 0 ? round($option->votes_count * 100 / $total, 2) : 0.0,
            ])->all(),
        ];
    }

    private function visibleTo(User $user): Builder
    {
        Gate::forUser($user)->authorize('viewAny', Poll::class);
        $query = Poll::query()->with('author:id,name');
        if ($user->role === UserRole::Morador) {
            $query->whereNotNull('published_at')->where('published_at', '<=', now());
        }

        return $query;
    }
}
