<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommonAreaBlockRequest;
use App\Models\CommonArea;
use App\Models\CommonAreaBlock;
use App\Services\CommonAreaBlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommonAreaBlockController extends Controller
{
    public function index(CommonAreaBlockService $service): Response
    {
        return Inertia::render('admin/common-area-blocks', [
            'blocks' => $service->listing(),
            'areas' => CommonArea::query()->where('status', 'active')->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(StoreCommonAreaBlockRequest $request, CommonAreaBlockService $service): JsonResponse
    {
        $service->create($request->user(), $request->validated());

        return response()->json(['message' => 'Bloqueio criado com sucesso.'], 201);
    }

    public function destroy(Request $request, CommonAreaBlock $commonAreaBlock, CommonAreaBlockService $service): JsonResponse
    {
        $service->remove($request->user(), $commonAreaBlock);

        return response()->json(['message' => 'Bloqueio removido com sucesso.']);
    }
}
