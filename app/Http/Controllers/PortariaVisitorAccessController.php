<?php

namespace App\Http\Controllers;

use App\Models\VisitorAccess;
use App\Services\VisitorAccessQueryService;
use App\Services\VisitorService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PortariaVisitorAccessController extends Controller
{
    public function __construct(
        private VisitorAccessQueryService $visitorAccessQueryService,
        private VisitorService $visitorService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::forUser($request->user())->authorize('viewAny', VisitorAccess::class);

        return Inertia::render('portaria/visitor-accesses/index', [
            'openAccesses' => $this->visitorAccessQueryService->openForPortaria(),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function registerExit(Request $request, VisitorAccess $visitorAccess): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('update', $visitorAccess);

        try {
            $this->visitorService->registerExit(
                visitorAccess: $visitorAccess,
                doormanId: $request->user(),
            );
        } catch (DomainException $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Saída registrada com sucesso.',
        ]);

        return back();
    }
}
