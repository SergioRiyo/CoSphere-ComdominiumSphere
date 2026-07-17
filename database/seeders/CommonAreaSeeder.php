<?php

namespace Database\Seeders;

use App\Models\CommonArea;
use Illuminate\Database\Seeder;

class CommonAreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (CommonArea::query()->exists()) {
            return;
        }

        CommonArea::factory()
            ->count(3)
            ->create();
    }
}
