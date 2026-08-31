<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteVisitorInvitationRequest;
use App\Http\Requests\StoreVisitorInvitationRequest;
use App\Models\VisitorAuthorization;
use App\Services\VisitorQrCodeService;
use App\Services\VisitorService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VisitorInvitationController extends Controller
{
    public function __construct(private VisitorService $visitorService, private VisitorQrCodeService $visitorQrCodeService) {}

    public function store(StoreVisitorInvitationRequest $request): RedirectResponse
    {
        [, $token] = $this->visitorService->createInvitation($request->user(), $request->validated());

        return back()->with('invitation_url', route('visitor-invitations.show', $token));
    }

    public function destroy(Request $request, VisitorAuthorization $visitorAuthorization): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('view', $visitorAuthorization);
        $this->visitorService->revokeInvitation($visitorAuthorization);

        return back();
    }

    public function show(string $token): Response
    {
        $this->available($token);

        return Inertia::render('visitor-invitations/show', ['token' => $token]);
    }

    public function complete(CompleteVisitorInvitationRequest $request, string $token): Response
    {
        try {
            $authorization = $this->visitorService->completeInvitation($token, $request->validated());
        } catch (DomainException) {
            abort(404);
        }

        return Inertia::render('visitor-invitations/completed', ['qr_svg' => $this->visitorQrCodeService->svg($authorization)]);
    }

    private function available(string $token): void
    {
        $a = VisitorAuthorization::where('invitation_token_hash', hash('sha256', $token))->first();
        if (! $a || $a->status->value !== 'pending_data' || $a->invitation_used_at || ! $a->invitation_expires_at?->isFuture() || ! $a->start_date->isFuture()) {
            abort(404);
        }
    }
}
