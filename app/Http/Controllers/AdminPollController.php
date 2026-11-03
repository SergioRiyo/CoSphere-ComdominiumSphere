<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePollRequest;
use App\Models\Poll;
use App\Models\PollOption;
use App\Services\PollQueryService;
use App\Services\PollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminPollController extends Controller
{
    public function index(Request $request, PollQueryService $query): Response
    {
        return Inertia::render('admin/polls', [
            'polls' => $query->paginate($request->user())->through(fn (Poll $poll): array => $this->serializePoll($poll)),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Poll::class);

        return Inertia::render('admin/poll-form', ['poll' => null, 'results' => null, 'timezone' => config('app.timezone')]);
    }

    public function show(Request $request, int $poll, PollQueryService $query): Response
    {
        $current = $query->find($request->user(), $poll);
        Gate::authorize('view', $current);

        return Inertia::render('admin/poll-form', [
            'poll' => [...$this->serializePoll($current), 'options' => $current->options->map(fn (PollOption $option): array => ['id' => $option->id, 'text' => $option->text])->all()],
            'results' => $current->published_at ? $query->results($request->user(), $current) : null,
            'timezone' => config('app.timezone'),
        ]);
    }

    public function store(SavePollRequest $request, PollService $service): RedirectResponse
    {
        return to_route('admin.polls.show', $service->save($request->user(), $request->validated()));
    }

    public function update(SavePollRequest $request, Poll $poll, PollService $service): RedirectResponse
    {
        $service->save($request->user(), $request->validated(), $poll);

        return to_route('admin.polls.show', $poll);
    }

    public function publish(Request $request, Poll $poll, PollService $service): RedirectResponse
    {
        $service->publish($request->user(), $poll);

        return to_route('admin.polls.show', $poll);
    }

    public function close(Request $request, Poll $poll, PollService $service): RedirectResponse
    {
        $service->close($request->user(), $poll);

        return to_route('admin.polls.show', $poll);
    }

    /** @return array<string, mixed> */
    private function serializePoll(Poll $poll): array
    {
        return [
            'id' => $poll->id,
            'question' => $poll->question,
            'author' => $poll->author->name,
            'status' => $poll->status()->value,
            'published_at' => $poll->published_at?->toISOString(),
            'starts_at' => $poll->starts_at?->toISOString(),
            'ends_at' => $poll->ends_at?->toISOString(),
            'created_at' => $poll->created_at->toISOString(),
        ];
    }
}
