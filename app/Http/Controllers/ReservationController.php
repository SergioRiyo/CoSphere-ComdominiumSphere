<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;

class ReservationController extends Controller
{
    public function store(StoreReservationRequest $request, ReservationService $service): JsonResponse
    {
        $reservation = $service->create($request->user(), $request->validated());

        return response()->json([
            'area' => $reservation->commonArea()->value('name'),
            'date' => $reservation->starts_at->toDateString(),
            'start' => $reservation->starts_at->format('H:i:s'),
            'end' => $reservation->ends_at->format('H:i:s'),
            'status' => $reservation->status->value,
            'status_label' => $reservation->status->label(),
        ], 201)->header('Cache-Control', 'private, no-store');
    }
}
