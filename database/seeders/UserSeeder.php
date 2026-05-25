<?php

namespace Database\Seeders;

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
            ['email' => 'morador@cosphere.test'],
            [
                'unit_id' => $unit->id,
                'name' => 'Morador',
                'role' => 'morador',
                'password' => 'password',
            ],
        );

        User::query()->firstOrCreate(
            ['email' => 'porteiro@cosphere.test'],
            [
                'name' => 'Porteiro',
                'role' => 'porteiro',
                'password' => 'password',
            ],
        );
    }
}
