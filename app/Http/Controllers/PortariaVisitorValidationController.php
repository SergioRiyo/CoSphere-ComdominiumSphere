<?php

namespace App\Http\Controllers;

use App\Http\Requests\ValidateVisitorAuthorizationRequest;
use App\Services\VisitorService;
use Illuminate\Http\JsonResponse;

class PortariaVisitorValidationController extends Controller
{
    public function __construct(private VisitorService $visitorService) {}

    public function __invoke(ValidateVisitorAuthorizationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json(
            $this->visitorService->validateAccessCode($validated['access_code']),
        );
    }
}
