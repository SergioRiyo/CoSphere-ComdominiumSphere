<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\LostFoundIndexRequest;
use App\Http\Requests\LostFoundItemRequest;
use App\Http\Requests\LostFoundMessageRequest;
use App\Http\Requests\LostFoundStatusRequest;
use App\Models\LostFoundConversation;
use App\Models\LostFoundItem;
use App\Services\LostFoundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LostFoundController extends Controller
{
    public function index(LostFoundIndexRequest $request, LostFoundService $service): Response
    {
        return Inertia::render('lost-found/index', $service->index($request->user(), $request->validated()));
    }

    public function show(Request $request, LostFoundItem $lostFoundItem, LostFoundService $service): Response
    {
        return Inertia::render('lost-found/show', ['item' => $service->detail($request->user(), $lostFoundItem)]);
    }

    public function store(LostFoundItemRequest $request, LostFoundService $service): RedirectResponse
    {
        $item = $service->create($request->user(), $request->validated());

        return redirect()->route($request->user()->role === UserRole::Admin ? 'admin.lost-found.show' : 'morador.lost-found.show', $item);
    }

    public function update(LostFoundItemRequest $request, LostFoundItem $lostFoundItem, LostFoundService $service): RedirectResponse
    {
        $service->update($request->user(), $lostFoundItem, $request->validated());

        return back()->with('success', 'Item atualizado.');
    }

    public function status(LostFoundStatusRequest $request, LostFoundItem $lostFoundItem, LostFoundService $service): RedirectResponse
    {
        $service->changeStatus($request->user(), $lostFoundItem, $request->validated('status'));

        return back()->with('success', 'Situação atualizada.');
    }

    public function message(LostFoundMessageRequest $request, LostFoundItem $lostFoundItem, LostFoundService $service): RedirectResponse
    {
        $service->startConversation($request->user(), $lostFoundItem, $request->validated('body'));

        return back()->with('success', 'Mensagem enviada à administração.');
    }

    public function reply(LostFoundMessageRequest $request, LostFoundItem $lostFoundItem, LostFoundConversation $lostFoundConversation, LostFoundService $service): RedirectResponse
    {
        $service->reply($request->user(), $lostFoundItem, $lostFoundConversation, $request->validated('body'));

        return back()->with('success', 'Mensagem enviada.');
    }

    public function photo(Request $request, LostFoundItem $lostFoundItem, LostFoundService $service): StreamedResponse
    {
        $service->authorizeUser($request->user());
        abort_unless($lostFoundItem->photo_path && Storage::disk(config('filesystems.lost_found_disk'))->exists($lostFoundItem->photo_path), 404);

        return Storage::disk(config('filesystems.lost_found_disk'))->response($lostFoundItem->photo_path, null, [
            'Content-Type' => $lostFoundItem->photo_mime, 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
