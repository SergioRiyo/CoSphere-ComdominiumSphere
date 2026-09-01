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
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class VisitorInvitationController extends Controller
{
    public function __construct(private VisitorService $visitorService, private VisitorQrCodeService $visitorQrCodeService) {}

    public function store(StoreVisitorInvitationRequest $request): RedirectResponse
    {
        [, $token] = $this->visitorService->createInvitation($request->user(), $request->validated());

        return back()->with('invitation_url', route('visitor-invitations.show', $token));
    }

    public function show(Request $request, string $token): HttpResponse
    {
        $this->available($token);

        return $this->withoutCache(
            Inertia::render('visitor-invitations/show', ['token' => $token])->toResponse($request),
        );
    }

    public function complete(CompleteVisitorInvitationRequest $request, string $token): HttpResponse
    {
        try {
            $authorization = $this->visitorService->completeInvitation($token, $request->validated());
        } catch (DomainException) {
            abort(404);
        }

        return $this->withoutCache(
            Inertia::render('visitor-invitations/completed', ['qr_svg' => $this->visitorQrCodeService->svg($authorization)])
                ->toResponse($request),
        );
    }

    private function available(string $token): void
    {
        $a = VisitorAuthorization::where('invitation_token_hash', hash('sha256', $token))->first();
        if (! $a || $a->status->value !== 'pending_data' || $a->invitation_used_at || ! $a->invitation_expires_at?->isFuture() || ! $a->start_date->isFuture()) {
            abort(404);
        }
    }

    private function withoutCache(HttpResponse $response): HttpResponse
    {
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
