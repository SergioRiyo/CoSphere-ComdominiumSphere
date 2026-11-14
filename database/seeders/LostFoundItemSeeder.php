<?php

namespace Database\Seeders;

use App\Models\LostFoundItem;
use Illuminate\Database\Seeder;

class LostFoundItemSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment(['local', 'testing']) && ! LostFoundItem::query()->exists()) {
            LostFoundItem::factory()->count(3)->create();
        }
    }
}
