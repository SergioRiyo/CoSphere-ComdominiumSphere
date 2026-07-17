<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberta',
            self::InProgress => 'Em andamento',
            self::Completed => 'Finalizada',
            self::Canceled => 'Cancelada',
        };
    }
}