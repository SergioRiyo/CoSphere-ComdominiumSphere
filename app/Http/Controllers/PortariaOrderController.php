<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexPortariaOrderRequest;
use App\Http\Requests\ReceiveOrderRequest;
use App\Http\Requests\StoreUnexpectedOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PortariaOrderQueryService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PortariaOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PortariaOrderQueryService $queryService,
    ) {}

    public function index(IndexPortariaOrderRequest $request): Response
    {
        $filters = $request->validated();
        $unitId = isset($filters['unit_id']) ? (int) $filters['unit_id'] : null;

        return Inertia::render('portaria/orders/index', [
            'orders' => $this->queryService->expectedOrders($filters),
            'unitOptions' => $this->queryService->unitOptions(),
            'residentOptions' => $this->queryService->residentOptions($unitId),
            'filters' => ['unit_id' => $unitId, 'search' => $filters['search'] ?? ''],
        ]);
    }

    public function receive(ReceiveOrderRequest $request, Order $order): RedirectResponse
    {
        $this->orderService->receive($order, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => "Encomenda #{$order->id} recebida. Destinatário notificado."]);

        return back();
    }

    public function store(StoreUnexpectedOrderRequest $request): RedirectResponse
    {
        $order = $this->orderService->createUnexpectedByDoorman($request->validated(), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => "Encomenda #{$order->id} cadastrada e recebida. Destinatário notificado."]);

        return to_route('portaria.orders.index', ['unit_id' => $order->unit_id]);
    }
}
