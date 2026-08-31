<?php

namespace App\Http\Controllers;

use App\Http\Requests\ValidateVisitorAuthorizationRequest;
use App\Services\VisitorService;
use DomainException;
use Illuminate\Http\JsonResponse;

class PortariaVisitorEntryController extends Controller
{
    public function __construct(private VisitorService $visitorService) {}

    public function __invoke(ValidateVisitorAuthorizationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $access = $this->visitorService->registerEntry(
                accessCode: $validated['access_code'],
                doormanId: $request->user(),
            );
        } catch (DomainException $exception) {
            return response()->json([
                'registered' => false,
                'message' => $exception->getMessage(),
                'entry' => null,
            ]);
        }

        return response()->json([
            'registered' => true,
            'message' => 'Entrada registrada com sucesso.',
            'entry' => [
                'entry_time' => $access->entry_time->toIso8601String(),
            ],
        ], 201);
    }
}
