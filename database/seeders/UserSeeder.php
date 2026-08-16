<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $unit = Unit::query()->first() ?? Unit::factory()->create();

        User::query()->firstOrCreate(
            ['email' => 'admin@cosphere.test'],
            [
                'name' => 'Administrador',
                'cpf' => '529.982.247-25',
                'phone' => '(65) 90000-0001',
                'role' => UserRole::Admin,
                'is_active' => true,
                'email_verified_at' => now(),
                'password' => 'password',
            ],
        );

        User::query()->firstOrCreate(
            ['email' => 'morador@cosphere.test'],
            [
                'unit_id' => $unit->id,
                'name' => 'Morador',
                'cpf' => '111.444.777-35',
                'phone' => '(65) 90000-0002',
                'role' => UserRole::Morador,
                'is_active' => true,
                'email_verified_at' => now(),
                'password' => 'password',
            ],
        );

        User::query()->firstOrCreate(
            ['email' => 'porteiro@cosphere.test'],
            [
                'name' => 'Porteiro',
                'cpf' => '123.456.789-09',
                'phone' => '(65) 90000-0003',
                'role' => UserRole::Porteiro,
                'is_active' => true,
                'email_verified_at' => now(),
                'password' => 'password',
            ],
        );
    }
}
