<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        if (Unit::query()->doesntExist() || User::query()->doesntExist()) {
            return;
        }

        Order::factory()
            ->count(20)
            ->create();
    }
}
