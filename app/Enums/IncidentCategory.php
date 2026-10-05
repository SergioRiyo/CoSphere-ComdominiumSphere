<?php

namespace App\Enums;

enum IncidentCategory: string
{
    case Maintenance = 'maintenance';
    case Security = 'security';
    case Cleaning = 'cleaning';
    case Noise = 'noise';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Maintenance => 'Manutenção',
            self::Security => 'Segurança',
            self::Cleaning => 'Limpeza',
            self::Noise => 'Barulho',
            self::Other => 'Outros',
        };
    }
}
