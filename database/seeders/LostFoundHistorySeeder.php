<?php

namespace Database\Seeders;

use App\Models\LostFoundHistory;
use Illuminate\Database\Seeder;

class LostFoundHistorySeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment(['local', 'testing']) && ! LostFoundHistory::query()->exists()) {
            LostFoundHistory::factory()->create();
        }
    }
}
