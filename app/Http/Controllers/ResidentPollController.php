<?php

namespace App\Http\Controllers;

use App\Http\Requests\CastPollVoteRequest;
use App\Models\Poll;
use App\Models\PollOption;
use App\Services\PollQueryService;
use App\Services\PollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ResidentPollController extends Controller
{
    public function index(Request $request, PollQueryService $query): Response
    {
        $state = $request->validate(['state' => ['sometimes', 'string', 'in:open,closed']])['state'] ?? 'open';

        return Inertia::render('morador/polls/index', [
            'polls' => $query->paginateForResident($request->user(), $state)->through(fn (Poll $poll): array => [
                'id' => $poll->id, 'question' => $poll->question, 'status' => $poll->status()->value,
                'starts_at' => $poll->starts_at?->toISOString(), 'ends_at' => $poll->ends_at?->toISOString(),
                'has_voted' => (bool) $poll->has_voted,
            ]),
            'state' => $state,
            'timezone' => config('app.timezone'),
        ]);
    }

    public function show(Request $request, int $poll, PollQueryService $query): Response
    {
        $current = $query->find($request->user(), $poll);
        Gate::authorize('view', $current);

        return Inertia::render('morador/polls/show', [
            'poll' => [
                'id' => $current->id, 'question' => $current->question, 'status' => $current->status()->value,
                'starts_at' => $current->starts_at?->toISOString(), 'ends_at' => $current->ends_at?->toISOString(),
                'has_voted' => $query->hasVoted($request->user(), $current),
                'options' => $current->options->map(fn (PollOption $option): array => ['id' => $option->id, 'text' => $option->text])->all(),
            ],
            'timezone' => config('app.timezone'),
        ]);
    }

    public function vote(CastPollVoteRequest $request, int $poll, PollQueryService $query, PollService $service): RedirectResponse
    {
        $current = $query->find($request->user(), $poll);
        $service->vote($request->user(), $current, (int) $request->validated('option_id'));

        return to_route('morador.polls.show', $current);
    }
}
