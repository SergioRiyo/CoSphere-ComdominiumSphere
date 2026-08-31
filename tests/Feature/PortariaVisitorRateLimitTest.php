<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortariaVisitorRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_is_rate_limited_per_authenticated_doorman_and_ip(): void
    {
        $doorman = User::factory()->porteiro()->create();
        $route = route('portaria.visitor-authorizations.validate');

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->actingAs($doorman)
                ->postJson($route, ['access_code' => 'csa_codigo_inexistente'])
                ->assertOk();
        }

        $this->actingAs($doorman)
            ->postJson($route, ['access_code' => 'csa_codigo_inexistente'])
            ->assertTooManyRequests();
    }
}
