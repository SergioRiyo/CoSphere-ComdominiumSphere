<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexReservationRequest;
use App\Http\Requests\RejectReservationRequest;
use App\Models\Reservation;
use App\Services\ReservationQueryService;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminReservationController extends Controller
{
    public function index(IndexReservationRequest $request, ReservationQueryService $service): Response
    {
        return Inertia::render('admin/reservations', [
            'reservations' => $service->paginate($request->user(), $request->validated()),
            'filters' => $request->safe()->only(['status', 'date_from', 'date_to']),
            'statuses' => $service->statuses(),
        ]);
    }

    public function show(Request $request, int $reservation, ReservationQueryService $service): Response
    {
        return Inertia::render('admin/reservation-details', ['reservation' => $service->details($request->user(), $reservation)]);
    }

    public function approve(Request $request, Reservation $reservation, ReservationService $service): JsonResponse
    {
        $service->approve($request->user(), $reservation);

        return response()->json(['message' => 'Reserva aprovada com sucesso.']);
    }

    public function reject(RejectReservationRequest $request, Reservation $reservation, ReservationService $service): JsonResponse
    {
        $service->reject($request->user(), $reservation, $request->validated('rejection_reason'));

        return response()->json(['message' => 'Reserva recusada.']);
    }

    public function cancel(Request $request, Reservation $reservation, ReservationService $service): JsonResponse
    {
        $service->cancelByAdmin($request->user(), $reservation);

        return response()->json(['message' => 'Reserva cancelada.']);
    }
}
