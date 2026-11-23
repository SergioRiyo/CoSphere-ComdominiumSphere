<?php

namespace Tests\Feature;

use Database\Seeders\UserSeeder;
use LogicException;
use Tests\TestCase;

class ProductionSeederSafetyTest extends TestCase
{
    public function test_development_users_cannot_be_seeded_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(LogicException::class);

        app(UserSeeder::class)->run();
    }
}
