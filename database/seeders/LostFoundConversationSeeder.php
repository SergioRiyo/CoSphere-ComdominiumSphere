<?php

namespace Database\Seeders;

use App\Models\LostFoundConversation;
use Illuminate\Database\Seeder;

class LostFoundConversationSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment(['local', 'testing']) && ! LostFoundConversation::query()->exists()) {
            LostFoundConversation::factory()->create();
        }
    }
}
