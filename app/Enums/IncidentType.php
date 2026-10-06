<?php

namespace App\Enums;

enum IncidentType: string
{
    case Incident = 'incident';
    case MaintenanceRequest = 'maintenance_request';

    public function label(): string
    {
        return match ($this) {
            self::Incident => 'Ocorrência',
            self::MaintenanceRequest => 'Solicitação de manutenção',
        };
    }
}
