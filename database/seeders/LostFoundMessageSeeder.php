<?php

namespace Database\Seeders;

use App\Models\LostFoundMessage;
use Illuminate\Database\Seeder;

class LostFoundMessageSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment(['local', 'testing']) && ! LostFoundMessage::query()->exists()) {
            LostFoundMessage::factory()->create();
        }
    }
}
