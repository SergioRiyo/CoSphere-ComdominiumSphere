<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Morador = 'morador';
    case Porteiro = 'porteiro';
}
