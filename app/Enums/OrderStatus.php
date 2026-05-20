<?php

namespace App\Enums;

enum OrderStatus: string
{
    case WaitingDelivery = 'waiting_delivery';
    case ReceivedAtGate = 'received_at_gate';
    case PickedUp = 'picked_up';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::WaitingDelivery => 'Aguardando entrega',
            self::ReceivedAtGate => 'Recebida na portaria',
            self::PickedUp => 'Retirada',
            self::Cancelled => 'Cancelada',
        };
    }
}