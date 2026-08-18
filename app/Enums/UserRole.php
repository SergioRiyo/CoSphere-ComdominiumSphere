<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Morador = 'morador';
    case Porteiro = 'porteiro';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Morador => 'Morador',
            self::Porteiro => 'Porteiro',
        };
    }
}
