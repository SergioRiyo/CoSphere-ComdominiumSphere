<?php

namespace App\Enums;

enum MaintenanceRequestStatus: string
{
    case Pending = 'pending';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Scheduled => 'Agendada',
            self::InProgress => 'Em andamento',
            self::Completed => 'Finalizada',
            self::Canceled => 'Cancelada',
        };
    }
}