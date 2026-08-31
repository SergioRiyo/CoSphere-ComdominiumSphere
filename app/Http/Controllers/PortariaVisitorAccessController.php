<?php

namespace App\Http\Controllers;

use App\Models\VisitorAccess;
use App\Services\VisitorAccessQueryService;
use App\Services\VisitorService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortariaVisitorAccessController extends Controller
{
    public function __construct(
        private VisitorAccessQueryService $visitorAccessQueryService,
        private VisitorService $visitorService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('portaria/visitor-accesses/index', [
            'openAccesses' => $this->visitorAccessQueryService->openForPortaria(),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function registerExit(Request $request, VisitorAccess $visitorAccess): RedirectResponse
    {
        try {
            $this->visitorService->registerExit(
                visitorAccess: $visitorAccess,
                doormanId: (int) $request->user()->getAuthIdentifier(),
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
