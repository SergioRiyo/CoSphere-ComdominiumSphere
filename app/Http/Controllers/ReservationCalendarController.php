<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReservationCalendarRequest;
use App\Services\ReservationCalendarService;
use Illuminate\Http\JsonResponse;

class ReservationCalendarController extends Controller
{
    public function __invoke(ReservationCalendarRequest $request, ReservationCalendarService $service): JsonResponse
    {
        return response()->json($service->data($request->user(), $request->validated()))
            ->header('Cache-Control', 'private, no-store');
    }
}
