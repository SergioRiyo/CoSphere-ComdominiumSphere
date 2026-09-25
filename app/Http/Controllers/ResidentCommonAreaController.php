<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommonAreaAvailabilityRequest;
use App\Models\CommonArea;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ResidentCommonAreaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('morador/common-areas', [
            'areas' => CommonArea::query()->where('status', 'active')->orderBy('name')->orderBy('id')
                ->get(['id', 'name'])->map(fn (CommonArea $area): array => $area->only(['id', 'name'])),
            'today' => now()->toDateString(),
        ]);
    }

    public function availability(CommonAreaAvailabilityRequest $request, CommonArea $commonArea, ReservationService $service): JsonResponse
    {
        return response()->json([
            'area' => $commonArea->only([
                'id', 'name', 'description', 'available_from', 'available_until',
                'max_reservation_minutes', 'rules', 'requires_approval',
            ]),
            ...$service->availability($commonArea, Carbon::createFromFormat('!Y-m-d', $request->validated('date'))),
        ])->header('Cache-Control', 'private, no-store');
    }
}
