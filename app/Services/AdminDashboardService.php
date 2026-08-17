<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;

class AdminDashboardService
{
    /**
     * @return array{
     *     active_users: int,
     *     inactive_users: int,
     *     administrators: int,
     *     residents: int,
     *     doormen: int,
     *     units: int
     * }
     */
    public function metrics(): array
    {
        $userMetrics = User::query()
            ->toBase()
            ->selectRaw(
                <<<'SQL'
                    COUNT(CASE WHEN is_active THEN 1 END) AS active_users,
                    COUNT(CASE WHEN NOT is_active THEN 1 END) AS inactive_users,
                    COUNT(CASE WHEN role = ? THEN 1 END) AS administrators,
                    COUNT(CASE WHEN role = ? THEN 1 END) AS residents,
                    COUNT(CASE WHEN role = ? THEN 1 END) AS doormen
                SQL,
                [
                    UserRole::Admin->value,
                    UserRole::Morador->value,
                    UserRole::Porteiro->value,
                ],
            )
            ->first();

        return [
            'active_users' => (int) $userMetrics->active_users,
            'inactive_users' => (int) $userMetrics->inactive_users,
            'administrators' => (int) $userMetrics->administrators,
            'residents' => (int) $userMetrics->residents,
            'doormen' => (int) $userMetrics->doormen,
            'units' => Unit::query()->count(),
        ];
    }
}
