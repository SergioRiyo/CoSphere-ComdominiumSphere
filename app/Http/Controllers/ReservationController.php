<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexReservationRequest;
use App\Http\Requests\StoreReservationRequest;
use App\Models\Reservation;
use App\Services\ReservationQueryService;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function index(IndexReservationRequest $request, ReservationQueryService $service): Response
    {
        return Inertia::render('morador/reservations', [
            'reservations' => $service->paginate($request->user(), $request->validated()),
            'filters' => $request->safe()->only(['status', 'date_from', 'date_to']),
            'statuses' => $service->statuses(),
        ]);
    }

    public function show(Request $request, int $reservation, ReservationQueryService $service): Response
    {
        return Inertia::render('morador/reservation-details', ['reservation' => $service->details($request->user(), $reservation)]);
    }

    public function cancel(Request $request, Reservation $reservation, ReservationService $service): JsonResponse
    {
        $service->cancelByResident($request->user(), $reservation);

        return response()->json(['message' => 'Reserva cancelada.']);
    }

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
