<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        if (Order::query()->exists()) {
            return;
        }

        $resident = User::query()
            ->where('role', UserRole::Morador)
            ->whereNotNull('unit_id')
            ->first();
        $doorman = User::query()
            ->where('role', UserRole::Porteiro)
            ->first();

        if ($resident === null || $doorman === null) {
            $this->command->warn('Crie um morador vinculado a uma unidade e um porteiro antes das encomendas.');

            return;
        }

        Order::factory()
            ->count(10)
            ->state(function (array $attributes) use ($resident, $doorman): array {
                $status = OrderStatus::from($attributes['status']);

                return [
                    'received_by_id' => in_array($status, [
                        OrderStatus::ReceivedAtGate,
                        OrderStatus::PickedUp,
                    ], true) ? $doorman->id : null,
                    'picked_up_by_id' => $status === OrderStatus::PickedUp
                        ? $resident->id
                        : null,
                ];
            })
            ->create([
                'unit_id' => $resident->unit_id,
                'resident_id' => $resident->id,
            ]);
    }
}
