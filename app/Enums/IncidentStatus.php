<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Canceled = 'canceled';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, match ($this) {
            self::Open => [self::InProgress, self::Canceled],
            self::InProgress => [self::Completed, self::Canceled],
            self::Completed, self::Canceled => [],
        }, true);
    }

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
