<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAnnouncementRequest;
use App\Models\Announcement;
use App\Services\AnnouncementQueryService;
use App\Services\AnnouncementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(Request $request, AnnouncementQueryService $query): Response
    {
        return Inertia::render('admin/announcements', [
            'announcements' => $query->paginate($request->user())->through(fn (Announcement $announcement): array => $this->serializeAnnouncement($announcement, false)),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Announcement::class);

        return Inertia::render('admin/announcement-form', ['announcement' => null, 'timezone' => config('app.timezone')]);
    }

    public function show(Request $request, int $announcement, AnnouncementQueryService $query): Response
    {
        $current = $query->find($request->user(), $announcement);
        Gate::authorize('view', $current);

        return Inertia::render('admin/announcement-form', ['announcement' => $this->serializeAnnouncement($current), 'timezone' => config('app.timezone')]);
    }

    public function store(SaveAnnouncementRequest $request, AnnouncementService $service): RedirectResponse
    {
        $announcement = $service->save($request->user(), $request->validated());

        return to_route('admin.announcements.show', $announcement);
    }

    public function update(SaveAnnouncementRequest $request, Announcement $announcement, AnnouncementService $service): RedirectResponse
    {
        $service->save($request->user(), $request->validated(), $announcement);

        return to_route('admin.announcements.show', $announcement);
    }

    public function publish(Request $request, Announcement $announcement, AnnouncementService $service): RedirectResponse
    {
        $service->publish($request->user(), $announcement);

        return to_route('admin.announcements.show', $announcement);
    }

    /** @return array<string, mixed> */
    private function serializeAnnouncement(Announcement $announcement, bool $includeContent = true): array
    {
        $data = [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'author' => $announcement->author->name,
            'published_at' => $announcement->published_at?->toISOString(),
            'created_at' => $announcement->created_at->toISOString(),
        ];
        if ($includeContent) {
            $data['content'] = $announcement->content;
        }

        return $data;
    }
}
