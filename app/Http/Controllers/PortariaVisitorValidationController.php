<?php

namespace App\Http\Controllers;

use App\Http\Requests\ValidateVisitorAuthorizationRequest;
use App\Models\VisitorAccess;
use App\Services\VisitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PortariaVisitorValidationController extends Controller
{
    public function __construct(private VisitorService $visitorService) {}

    public function index(Request $request): Response
    {
        Gate::forUser($request->user())->authorize('viewAny', VisitorAccess::class);

        return Inertia::render('portaria/visitor-validation', [
            'timezone' => config('app.timezone'),
        ]);
    }

    public function __invoke(ValidateVisitorAuthorizationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json(
            $this->visitorService->validateAccessCode($validated['access_code']),
        );
    }
}
