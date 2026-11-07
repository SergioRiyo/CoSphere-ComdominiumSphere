<?php

namespace App\Services;

use App\Enums\PollStatus;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PollService
{
    /** @param array<string, mixed> $data */
    public function save(User $actor, array $data, ?Poll $poll = null): Poll
    {
        return DB::transaction(function () use ($actor, $data, $poll): Poll {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $current = $poll ? Poll::query()->lockForUpdate()->findOrFail($poll->id) : new Poll;
            Gate::forUser($actor)->authorize($poll ? 'update' : 'create', $poll ? $current : Poll::class);
            $validated = Validator::make($data, [
                'question' => ['required', 'string', 'max:255'],
                'options' => ['required', 'array', 'min:2'],
                'options.*' => ['required', 'string', 'max:255', 'distinct'],
            ])->validate();
            $current->fill(['question' => $validated['question']]);
            if (! $current->exists) {
                $current->author()->associate($actor);
            }
            $current->saveOrFail();
            $current->options()->delete();
            foreach (array_values($validated['options']) as $position => $text) {
                $current->options()->create(['text' => $text, 'position' => $position]);
            }

            return $current->load('options');
        });
    }

    public function publish(User $actor, Poll $poll): Poll
    {
        return DB::transaction(function () use ($actor, $poll): Poll {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $current = Poll::query()->lockForUpdate()->findOrFail($poll->id);
            Gate::forUser($actor)->authorize('publish', $current);
            if ($current->published_at !== null) {
                return $current;
            }
            if ($current->options()->count() < 2) {
                throw ValidationException::withMessages(['options' => 'A enquete precisa de pelo menos duas opções.']);
            }
            $current->published_at = now();
            $current->starts_at = $current->published_at;
            $current->ends_at = null;
            $current->saveOrFail();

            return $current;
        });
    }

    public function close(User $actor, Poll $poll): Poll
    {
        return DB::transaction(function () use ($actor, $poll): Poll {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $current = Poll::query()->lockForUpdate()->findOrFail($poll->id);
            Gate::forUser($actor)->authorize('close', $current);
            if ($current->status() === PollStatus::Closed) {
                return $current;
            }
            if ($current->status() !== PollStatus::Open) {
                throw ValidationException::withMessages(['poll' => 'Somente enquetes abertas podem ser encerradas.']);
            }
            $current->ends_at = now();
            $current->saveOrFail();

            return $current;
        });
    }

    public function vote(User $actor, Poll $poll, int $optionId): PollVote
    {
        try {
            return DB::transaction(function () use ($actor, $poll, $optionId): PollVote {
                $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
                $current = Poll::query()->lockForUpdate()->findOrFail($poll->id);
                Gate::forUser($actor)->authorize('vote', $current);
                if ($current->status() !== PollStatus::Open) {
                    throw ValidationException::withMessages(['poll' => 'Esta enquete não está aberta para votação.']);
                }
                if (! $current->options()->whereKey($optionId)->exists()) {
                    throw ValidationException::withMessages(['option' => 'A opção não pertence a esta enquete.']);
                }
                if ($current->votes()->where('user_id', $actor->id)->exists()) {
                    throw ValidationException::withMessages(['poll' => 'Você já votou nesta enquete.']);
                }

                return $current->votes()->create(['poll_option_id' => $optionId, 'user_id' => $actor->id]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['poll' => 'Você já votou nesta enquete.']);
        }
    }
}
