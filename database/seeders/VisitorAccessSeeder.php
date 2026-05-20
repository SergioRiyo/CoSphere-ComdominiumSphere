<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use Illuminate\Database\Seeder;

class VisitorAccessSeeder extends Seeder
{
    public function run(): void
    {
        if (User::count() === 0) {
            $this->command->warn('Nenhum usuário encontrado. Crie users antes de gerar acessos.');
            return;
        }

        if (VisitorAuthorization::count() === 0) {
            $this->command->warn('Nenhuma autorização encontrada. Crie visitor_authorizations antes de gerar acessos.');
            return;
        }

        VisitorAccess::factory()->count(10)->create();
    }
}
