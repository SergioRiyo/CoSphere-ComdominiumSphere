<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    public function test_database_seeder_creates_core_roles_and_structures(): void
    {
        $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true]);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@cosphere.test',
            'cpf' => '529.982.247-25',
            'phone' => '(65) 90000-0001',
            'role' => 'admin',
            'unit_id' => null,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'morador@cosphere.test',
            'cpf' => '111.444.777-35',
            'phone' => '(65) 90000-0002',
            'role' => 'morador',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'porteiro@cosphere.test',
            'cpf' => '123.456.789-09',
            'phone' => '(65) 90000-0003',
            'role' => 'porteiro',
            'unit_id' => null,
            'is_active' => true,
        ]);

        $this->assertTrue(Unit::query()->exists());
        $this->assertNotNull(User::query()->where('email', 'morador@cosphere.test')->value('unit_id'));
    }
}
