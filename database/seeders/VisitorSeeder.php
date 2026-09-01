<?php

namespace Database\Seeders;

use App\Models\Visitor;
use Illuminate\Database\Seeder;

class VisitorSeeder extends Seeder
{
    public function run(): void
    {
        if (Visitor::query()->exists()) {
            return;
        }

        Visitor::factory()->count(10)->create();
    }
}
