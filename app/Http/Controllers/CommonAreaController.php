<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommonAreaAvailabilityRequest;
use App\Http\Requests\StoreCommonAreaRequest;
use App\Http\Requests\UpdateCommonAreaRequest;
use App\Models\CommonArea;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CommonAreaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/common-areas', [
            'areas' => CommonArea::query()->orderBy('name')->orderBy('id')->paginate(15),
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

    public function store(StoreCommonAreaRequest $request): RedirectResponse
    {
        CommonArea::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Área cadastrada com sucesso.',
        ]);

        return back();
    }

    public function update(UpdateCommonAreaRequest $request, CommonArea $commonArea): RedirectResponse
    {
        $commonArea->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Área atualizada com sucesso.',
        ]);

        return back();
    }
}
