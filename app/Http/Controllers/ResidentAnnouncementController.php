<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\AnnouncementQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ResidentAnnouncementController extends Controller
{
    public function index(Request $request, AnnouncementQueryService $query): Response
    {
        return Inertia::render('morador/announcements/index', [
            'announcements' => $query->paginate($request->user())->through(fn (Announcement $announcement): array => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'author' => $announcement->author->name,
                'published_at' => $announcement->published_at->toISOString(),
            ]),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function show(Request $request, int $announcement, AnnouncementQueryService $query): Response
    {
        $current = $query->find($request->user(), $announcement);
        Gate::authorize('view', $current);

        return Inertia::render('morador/announcements/show', [
            'announcement' => [
                'id' => $current->id,
                'title' => $current->title,
                'content' => $current->content,
                'author' => $current->author->name,
                'published_at' => $current->published_at->toISOString(),
            ],
            'timezone' => config('app.timezone'),
        ]);
    }
}
