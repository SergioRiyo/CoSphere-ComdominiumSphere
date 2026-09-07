<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Database\Seeder;

class VisitorSeeder extends Seeder
{
    public function run(): void
    {
        if (Visitor::query()->exists()) {
            return;
        }

        $resident = User::query()->where('role', UserRole::Morador)->whereNotNull('unit_id')->first();

        if (! $resident) {
            $this->command->warn('Crie um morador vinculado a uma unidade antes dos visitantes.');

            return;
        }

        Visitor::factory()->count(10)->create(['unit_id' => $resident->unit_id]);
    }
}
