<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Approved = 'confirmed';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Approved => 'Aprovada',
            self::Cancelled => 'Cancelada',
            self::Rejected => 'Recusada',
            self::Completed => 'Concluida',
        };
    }
}
