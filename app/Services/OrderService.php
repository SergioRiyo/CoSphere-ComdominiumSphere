<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function createExpectedByResident(array $data, User $resident): Order
    {
        return DB::transaction(function () use ($data, $resident) {
            $resident = $resident->fresh() ?? $resident;

            $this->ensureResidentCanCreateExpectedOrder($resident);
            if (array_key_exists('unit_id', $data)) {
                $this->ensureUnitMatchesResident(
                    resident: $resident,
                    unitId: $data['unit_id'],
                );
            }

            return Order::create([
                'unit_id' => $resident->unit_id,
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
            $this->ensureDoorman($doorman);

            $resident = User::query()->lockForUpdate()->find($data['resident_id']);

            if (! $resident instanceof User) {
                throw ValidationException::withMessages([
                    'resident_id' => 'Morador não encontrado.',
                ]);
            }

            $this->ensureResidentCanReceiveOrders($resident);
            $this->ensureUnitMatchesResident(
                resident: $resident,
                unitId: $data['unit_id'] ?? null,
            );

            $order = Order::create([
                'unit_id' => $resident->unit_id,
                'resident_id' => $resident->id,

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
            $this->ensureDoorman($doorman);

            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $order->setRelation('resident', User::query()->lockForUpdate()->find($order->resident_id));
            $this->ensureOrderResidentMatchesUnit($order);
            $this->ensureResidentCanReceiveOrders($order->resident);
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
            $order = Order::query()
                ->with('resident')
                ->lockForUpdate()
                ->findOrFail($order->id);

            $this->ensureOrderResidentMatchesUnit($order);
            $this->ensureUserCanPickUpOrder($order, $user);
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
        if ($order->status !== OrderStatus::WaitingDelivery) {
            throw ValidationException::withMessages([
                'order' => 'Somente encomendas aguardando entrega podem ser recebidas.',
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

    private function ensureResidentCanCreateExpectedOrder(User $resident): void
    {
        $this->ensureResidentCanReceiveOrders($resident);

        if ($resident->unit_id === null || ! $resident->unit()->exists()) {
            throw ValidationException::withMessages([
                'resident' => 'O morador precisa estar vinculado a uma unidade.',
            ]);
        }
    }

    private function ensureResidentCanReceiveOrders(User $resident): void
    {
        if ($resident->role !== UserRole::Morador || ! $resident->is_active) {
            throw ValidationException::withMessages([
                'resident' => 'A encomenda deve estar vinculada a um morador ativo.',
            ]);
        }
    }

    private function ensureDoorman(User $doorman): void
    {
        if ($doorman->role !== UserRole::Porteiro || ! $doorman->is_active) {
            throw ValidationException::withMessages([
                'doorman' => 'Somente porteiros podem registrar encomendas na portaria.',
            ]);
        }
    }

    private function ensureUnitMatchesResident(User $resident, mixed $unitId): void
    {
        if ($resident->unit_id === null || ! $resident->unit()->exists()) {
            throw ValidationException::withMessages([
                'resident' => 'O morador precisa estar vinculado a uma unidade.',
            ]);
        }

        if ((int) $resident->unit_id !== (int) $unitId) {
            throw ValidationException::withMessages([
                'unit_id' => 'A unidade informada não corresponde ao morador selecionado.',
            ]);
        }
    }

    private function ensureOrderResidentMatchesUnit(Order $order): void
    {
        $resident = $order->resident;

        if (! $resident instanceof User || (int) $resident->unit_id !== (int) $order->unit_id) {
            throw ValidationException::withMessages([
                'order' => 'O destinatário não pertence mais à unidade original. Confirme o destino da encomenda antes de registrar um novo recebimento.',
            ]);
        }
    }

    private function ensureUserCanPickUpOrder(Order $order, User $user): void
    {
        if ($user->unit_id === null) {
            throw ValidationException::withMessages([
                'user' => 'O usuario precisa estar vinculado a unidade da encomenda.',
            ]);
        }

        if ((int) $user->unit_id !== (int) $order->unit_id) {
            throw ValidationException::withMessages([
                'user' => 'Somente moradores da unidade da encomenda podem retira-la.',
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
