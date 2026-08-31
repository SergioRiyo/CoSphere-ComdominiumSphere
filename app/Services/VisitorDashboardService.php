<?php

namespace App\Services;

use App\Enums\VisitorAccessStatus;
use App\Enums\VisitorAuthorizationStatus;
use App\Models\User;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;

class VisitorDashboardService
{
    public function activeAuthorizationsForResident(User $resident): int
    {
        if ($resident->unit_id === null) {
            return 0;
        }

        return VisitorAuthorization::query()
            ->where('unit_id', $resident->unit_id)
            ->where('status', VisitorAuthorizationStatus::Active)
            ->where('end_date', '>=', now())
            ->count();
    }

    public function presentVisitors(): int
    {
        return VisitorAccess::query()
            ->where('validation_status', VisitorAccessStatus::Validated)
            ->whereNotNull('entry_time')
            ->whereNull('exit_time')
            ->count();
    }
}
