<?php

namespace App\Http\Controllers;

use App\Enums\VisitorAuthorizationStatus;
use App\Http\Requests\IndexVisitorAuthorizationRequest;
use App\Http\Requests\StoreVisitorAuthorizationRequest;
use App\Models\VisitorAuthorization;
use App\Services\VisitorAuthorizationQueryService;
use App\Services\VisitorQrCodeService;
use App\Services\VisitorService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VisitorAuthorizationController extends Controller
{
    public function __construct(
        private VisitorAuthorizationQueryService $visitorAuthorizationQueryService,
        private VisitorQrCodeService $visitorQrCodeService,
        private VisitorService $visitorService,
    ) {}

    public function index(IndexVisitorAuthorizationRequest $request): Response
    {
        $filters = $request->validated();

        return Inertia::render('morador/visitors/index', [
            'authorizations' => $this->visitorAuthorizationQueryService
                ->paginateForResident($request->user(), $filters),
            'statusOptions' => array_map(
                static fn (VisitorAuthorizationStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                VisitorAuthorizationStatus::cases(),
            ),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
            ],
            'timezone' => config('app.timezone'),
            'invitationUrl' => $request->session()->get('invitation_url'),
        ]);
    }

    public function store(StoreVisitorAuthorizationRequest $request): RedirectResponse
    {
        try {
            $this->visitorService->createDirectAuthorization(
                $request->user(),
                $request->validated(),
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'start_date' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Visitante autorizado com sucesso.',
        ]);

        return back();
    }

    public function show(Request $request, VisitorAuthorization $visitorAuthorization): Response
    {
        Gate::forUser($request->user())->authorize('view', $visitorAuthorization);

        return Inertia::render('morador/visitors/show', [
            'authorization' => $this->visitorAuthorizationQueryService
                ->details($visitorAuthorization),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function qrCode(Request $request, VisitorAuthorization $visitorAuthorization): HttpResponse
    {
        Gate::forUser($request->user())->authorize('view', $visitorAuthorization);

        $response = response($this->visitorQrCodeService->svg($visitorAuthorization))
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff');

        if ($request->boolean('download')) {
            $response->header(
                'Content-Disposition',
                'attachment; filename="visitor-authorization-'.$visitorAuthorization->id.'.svg"',
            );
        }

        return $response;
    }

    public function accessCode(Request $request, VisitorAuthorization $visitorAuthorization): HttpResponse
    {
        Gate::forUser($request->user())->authorize('view', $visitorAuthorization);

        return response($this->visitorQrCodeService->manualCode($visitorAuthorization))
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
