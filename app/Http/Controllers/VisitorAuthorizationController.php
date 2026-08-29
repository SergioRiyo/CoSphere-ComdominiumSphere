<?php

namespace App\Http\Controllers;

use App\Enums\VisitorAuthorizationStatus;
use App\Http\Requests\IndexVisitorAuthorizationRequest;
use App\Models\VisitorAuthorization;
use App\Services\VisitorAuthorizationQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VisitorAuthorizationController extends Controller
{
    public function __construct(
        private VisitorAuthorizationQueryService $visitorAuthorizationQueryService,
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
        ]);
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
}
