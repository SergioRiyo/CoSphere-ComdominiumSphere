<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexOrderHistoryRequest;
use App\Http\Requests\PickupOrderRequest;
use App\Http\Requests\StoreExpectedOrderRequest;
use App\Models\Order;
use App\Services\OrderHistoryService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResidentOrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService, private readonly OrderHistoryService $historyService) {}

    public function index(IndexOrderHistoryRequest $request): Response
    {
        $unit = $request->user()->unit;

        return Inertia::render('morador/orders/index', [
            'unit' => $unit?->only(['id', 'block', 'number']),
            'timezone' => config('app.timezone'),
            'orders' => $this->historyService->paginate($request->user(), $request->validated()),
            'filters' => $this->historyService->filters($request->validated()),
            'statusOptions' => $this->historyService->statusOptions(),
        ]);
    }

    public function store(StoreExpectedOrderRequest $request): RedirectResponse
    {
        $order = $this->orderService->createExpectedByResident($request->validated(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Encomenda #{$order->id} cadastrada. Aguardando entrega.",
        ]);

        return to_route('morador.orders.index');
    }

    public function show(Request $request, int $order): Response
    {
        return Inertia::render('orders/show', [
            'order' => $this->historyService->details($request->user(), $order),
            'timezone' => config('app.timezone'),
            'portaria' => false,
        ]);
    }

    public function pickup(PickupOrderRequest $request, Order $order): RedirectResponse
    {
        $this->orderService->pickup($order, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => "Retirada da encomenda #{$order->id} confirmada."]);

        return back();
    }
}
