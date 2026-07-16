<?php

namespace Tests\Feature;

use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    public function test_database_seeder_creates_core_roles_and_structures(): void
    {
        $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true]);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@cosphere.test',
            'role' => 'admin',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'morador@cosphere.test',
            'role' => 'morador',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'porteiro@cosphere.test',
            'role' => 'porteiro',
        ]);

        $this->assertDatabaseHas('units', [
            'type' => 'apartamento',
        ]);
    }
}
