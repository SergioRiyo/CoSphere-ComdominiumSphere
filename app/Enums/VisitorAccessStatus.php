<?php

namespace App\Enums;

enum VisitorAccessStatus: string
{
    case Pending = 'pending';
    case Validated = 'validated';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando',
            self::Validated => 'Validado',
            self::Rejected => 'Rejeitado',
        };
    }
}