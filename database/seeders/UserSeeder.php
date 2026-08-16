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
                'role' => UserRole::Porteiro,
                'is_active' => true,
                'email_verified_at' => now(),
                'password' => 'password',
            ],
        );
    }
}
