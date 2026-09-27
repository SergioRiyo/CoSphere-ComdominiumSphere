<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function index(Request $request): Response
    {
        return Inertia::render('morador/notifications/index', [
            'notifications' => $this->notificationService->paginateForUser($request->user()),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function read(Request $request, int $notification): RedirectResponse
    {
        $this->notificationService->markAsRead($request->user(), $notification);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Notificação marcada como lida.',
        ]);

        return back();
    }
}
