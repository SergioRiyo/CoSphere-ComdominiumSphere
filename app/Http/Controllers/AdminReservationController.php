<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectReservationRequest;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminReservationController extends Controller
{
    public function index(ReservationService $service): Response
    {
        return Inertia::render('admin/reservations', ['reservations' => $service->operationalReservations()]);
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
