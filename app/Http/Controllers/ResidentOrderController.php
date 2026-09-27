<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpectedOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResidentOrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function index(Request $request): Response
    {
        $unit = $request->user()->unit;

        return Inertia::render('morador/orders/index', [
            'unit' => $unit?->only(['id', 'block', 'number']),
            'orders' => Order::query()
                ->where('unit_id', $unit?->id)
                ->orderByDesc('id')
                ->paginate(10)
                ->through(static fn (Order $order): array => [
                    'id' => $order->id,
                    'description' => $order->description,
                    'carrier' => $order->carrier,
                    'sender' => $order->sender,
                    'tracking_code' => $order->tracking_code,
                    'status' => $order->status->value,
                    'status_label' => $order->status->label(),
                ]),
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
}
