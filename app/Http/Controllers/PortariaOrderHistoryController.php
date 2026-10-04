<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexOrderHistoryRequest;
use App\Services\OrderHistoryService;
use App\Services\PortariaOrderQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortariaOrderHistoryController extends Controller
{
    public function __construct(private readonly OrderHistoryService $historyService) {}

    public function index(IndexOrderHistoryRequest $request, PortariaOrderQueryService $queryService): Response
    {
        return Inertia::render('portaria/order-history/index', [
            'orders' => $this->historyService->paginate($request->user(), $request->validated()),
            'filters' => $this->historyService->filters($request->validated()),
            'statusOptions' => $this->historyService->statusOptions(),
            'unitOptions' => $queryService->unitOptions(),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function show(Request $request, int $order): Response
    {
        return Inertia::render('orders/show', [
            'order' => $this->historyService->details($request->user(), $order),
            'timezone' => config('app.timezone'),
            'portaria' => true,
        ]);
    }
}
