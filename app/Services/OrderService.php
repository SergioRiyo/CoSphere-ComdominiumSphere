<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\NotificationService;

class OrderService
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {
    }

    public function createExpectedByResident(array $data, User $resident): Order
    {
        return DB::transaction(function () use ($data, $resident) {
            return Order::create([
                'unit_id' => $data['unit_id'],
                'resident_id' => $resident->id,

                'received_by_id' => null,
                'picked_up_by_id' => null,

                'tracking_code' => $data['tracking_code'] ?? null,
                'sender' => $data['sender'] ?? null,
                'carrier' => $data['carrier'] ?? null,
                'description' => $data['description'] ?? null,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,

                'received_at' => null,
                'picked_up_at' => null,
                'status' => OrderStatus::WaitingDelivery,
            ]);
        });
    }

    public function createUnexpectedByDoorman(array $data, User $doorman): Order
    {
        return DB::transaction(function () use ($data, $doorman) {
            $order = Order::create([
                'unit_id' => $data['unit_id'],
                'resident_id' => $data['resident_id'],

                'received_by_id' => $doorman->id,
                'picked_up_by_id' => null,

                'tracking_code' => $data['tracking_code'] ?? null,
                'sender' => $data['sender'] ?? null,
                'carrier' => $data['carrier'] ?? null,
                'description' => $data['description'] ?? null,
                'expected_delivery_date' => null,

                'received_at' => now(),
                'picked_up_at' => null,
                'status' => OrderStatus::ReceivedAtGate,
            ]);

            $this->notifyResidentOrderReceived($order);

            return $order;
        });
    }

    public function receive(Order $order, User $doorman): Order
    {
        return DB::transaction(function () use ($order, $doorman) {
            $this->ensureCanBeReceived($order);

            $order->update([
                'received_by_id' => $doorman->id,
                'received_at' => now(),
                'status' => OrderStatus::ReceivedAtGate,
            ]);

            $order = $order->fresh();

            $this->notifyResidentOrderReceived($order);

            return $order;
        });
    }

    public function pickup(Order $order, User $user): Order
    {
        return DB::transaction(function () use ($order, $user) {
            $this->ensureCanBePickedUp($order);

            $order->update([
                'picked_up_by_id' => $user->id,
                'picked_up_at' => now(),
                'status' => OrderStatus::PickedUp,
            ]);

            return $order->fresh();
        });
    }

    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            if ($order->status === OrderStatus::PickedUp) {
                throw ValidationException::withMessages([
                    'order' => 'Não é possível cancelar uma encomenda que já foi retirada.',
                ]);
            }

            if ($order->status === OrderStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'order' => 'Esta encomenda já está cancelada.',
                ]);
            }

            $order->update([
                'status' => OrderStatus::Cancelled,
            ]);

            return $order->fresh();
        });
    }

    private function ensureCanBeReceived(Order $order): void
    {
        if ($order->status === OrderStatus::ReceivedAtGate) {
            throw ValidationException::withMessages([
                'order' => 'Esta encomenda já foi recebida na portaria.',
            ]);
        }

        if ($order->status === OrderStatus::PickedUp) {
            throw ValidationException::withMessages([
                'order' => 'Esta encomenda já foi retirada.',
            ]);
        }

        if ($order->status === OrderStatus::Cancelled) {
            throw ValidationException::withMessages([
                'order' => 'Esta encomenda está cancelada.',
            ]);
        }
    }

    private function ensureCanBePickedUp(Order $order): void
    {
        if ($order->status !== OrderStatus::ReceivedAtGate) {
            throw ValidationException::withMessages([
                'order' => 'A encomenda precisa estar recebida na portaria para ser retirada.',
            ]);
        }
    }

    private function notifyResidentOrderReceived(Order $order): void
    {
        $trackingCode = $order->tracking_code
            ? " Código de rastreio: {$order->tracking_code}."
            : '';

        $description = $order->description
            ? " Descrição: {$order->description}."
            : '';

        $this->notificationService->create(
            $order->resident_id,
            'Encomenda recebida na portaria',
            "Sua encomenda foi recebida na portaria e está disponível para retirada.{$trackingCode}{$description}",
            NotificationType::Package
        );
    }
}