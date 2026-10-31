<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAnnouncementRequest;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function store(SaveAnnouncementRequest $request, AnnouncementService $service): RedirectResponse
    {
        $service->save($request->user(), $request->validated());

        return back();
    }

    public function update(SaveAnnouncementRequest $request, Announcement $announcement, AnnouncementService $service): RedirectResponse
    {
        $service->save($request->user(), $request->validated(), $announcement);

        return back();
    }

    public function publish(Request $request, Announcement $announcement, AnnouncementService $service): RedirectResponse
    {
        $service->publish($request->user(), $announcement);

        return back();
    }
}
