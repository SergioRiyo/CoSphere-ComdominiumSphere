<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

class RenderProxyTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_render_uses_the_forwarded_protocol_without_trusting_host_or_client_ip(): void
    {
        config(['trustedproxy.proxies' => '*']);
        Route::get('/proxy-check', fn (Request $request) => [
            'asset' => asset('build/example.js'),
            'client_ip' => $request->ip(),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'attacker.example',
                'X-Forwarded-For' => '203.0.113.5',
            ])
            ->get('http://example.test/proxy-check')
            ->assertOk()
            ->assertJson(['asset' => 'https://example.test/build/example.js', 'client_ip' => '10.0.0.1']);
    }

    #[RunInSeparateProcess]
    public function test_non_render_requests_do_not_trust_forwarded_protocol(): void
    {
        config(['trustedproxy.proxies' => null]);
        Route::get('/proxy-check', fn () => ['asset' => asset('build/example.js')]);

        $this->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://example.test/proxy-check')
            ->assertJson(['asset' => 'http://example.test/build/example.js']);
    }
}
