<?php

namespace App\Enums;

enum MaintenanceRequestStatus: string
{
    case Pending = 'pending';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Canceled = 'canceled';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, match ($this) {
            self::Pending => [self::Scheduled, self::InProgress, self::Canceled],
            self::Scheduled => [self::InProgress, self::Completed, self::Canceled],
            self::InProgress => [self::Completed, self::Canceled],
            self::Completed, self::Canceled => [],
        }, true);
    }

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
