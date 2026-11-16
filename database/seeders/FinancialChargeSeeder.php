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
        if (! app()->environment(['local', 'testing'])) {
            return;
        }
        $admin = User::query()->where('role', UserRole::Admin)->where('is_active', true)->whereNotNull('email_verified_at')->first();
        $units = Unit::query()->whereHas('users', fn ($query) => $query->where('role', UserRole::Morador))->orderBy('id')->get();
        if ($admin === null || $units->isEmpty()) {
            return;
        }
        $service = app(FinancialChargeService::class);
        $descriptions = ['Taxa condominial', 'Fundo de reserva', 'Manutenção de áreas comuns', 'Consumo de água'];
        for ($index = 0; $index < 16; $index++) {
            $description = sprintf('Simulação %02d - %s', $index + 1, $descriptions[$index % count($descriptions)]);
            if (FinancialCharge::query()->where('description', $description)->exists()) {
                continue;
            }

            $status = $index % 4;
            $charge = $service->create($admin, [
                'unit_id' => $units[$index % $units->count()]->id,
                'description' => $description,
                'amount' => number_format(150 + ($index * 15), 2, '.', ''),
                'due_date' => today()->addDays($status === 0 ? 15 + $index : -10 - $index)->toDateString(),
            ]);

            if ($status === 2) {
                $service->change($admin, $charge, ['action' => 'pay', 'paid_at' => today()->subDays(2)->toDateString()]);
            } elseif ($status === 3) {
                $service->change($admin, $charge, ['action' => 'cancel', 'reason' => 'Cancelamento demonstrativo']);
            }
        }
    }
}
