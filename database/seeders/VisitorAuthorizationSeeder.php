<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\User;
use App\Models\VisitorAuthorization;
use Illuminate\Database\Seeder;

class VisitorAuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        if (Unit::count() === 0) {
            $this->command->warn('Nenhuma unidade encontrada. Crie units antes de gerar autorizações.');
            return;
        }

        if (User::count() === 0) {
            $this->command->warn('Nenhum usuário encontrado. Crie users antes de gerar autorizações.');
            return;
        }

        VisitorAuthorization::factory()->count(10)->create();
    }
}
