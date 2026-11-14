<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\FinancialCharge;
use App\Models\Unit;
use App\Models\User;
use App\Services\FinancialChargeService;
use Illuminate\Database\Seeder;

class FinancialChargeSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || FinancialCharge::query()->exists()) {
            return;
        }
        $admin = User::query()->where('role', UserRole::Admin)->where('is_active', true)->whereNotNull('email_verified_at')->first();
        $units = Unit::query()->whereHas('users', fn ($query) => $query->where('role', UserRole::Morador))->limit(5)->get();
        if ($admin === null) {
            return;
        }
        $service = app(FinancialChargeService::class);
        foreach ($units as $unit) {
            foreach ([[-10, 'Taxa condominial demonstrativa vencida'], [15, 'Taxa condominial demonstrativa']] as [$offset, $description]) {
                $service->create($admin, ['unit_id' => $unit->id, 'description' => $description, 'amount' => '250.00', 'due_date' => today()->addDays($offset)->toDateString()]);
            }
        }
    }
}
