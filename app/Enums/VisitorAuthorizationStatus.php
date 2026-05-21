<?php

namespace App\Enums;

enum VisitorAuthorizationStatus: string
{
    case PendingData = 'pending_data';
    case Active = 'active';
    case Used = 'used';
    case Expired = 'expired';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::PendingData => 'Aguardando dados',
            self::Active => 'Ativa',
            self::Used => 'Utilizada',
            self::Expired => 'Expirada',
            self::Canceled => 'Cancelada',
        };
    }
}