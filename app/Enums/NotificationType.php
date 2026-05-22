<?php

namespace App\Enums;

enum NotificationType: string
{
    case Reservation = 'reservation';
    case Visitor = 'visitor';
    case Package = 'package';
    case Occurrence = 'occurrence';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Reservation => 'Reserva',
            self::Visitor => 'Visitante',
            self::Package => 'Encomenda',
            self::Occurrence => 'Ocorrência',
            self::System => 'Sistema',
        };
    }
}
