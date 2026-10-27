<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Ssr\Gateway;
use Inertia\Ssr\Response;
use Mockery\MockInterface;
use Tests\TestCase;

class InertiaRequestIsolationTest extends TestCase
{
    public function test_feature_pages_do_not_contact_a_live_development_ssr_server(): void
    {
        Http::preventStrayRequests();
        $this->get(route('home'))->assertOk();
        Http::assertNothingSent();
    }

    public function test_each_request_renders_its_own_ssr_html_in_the_same_test_application(): void
    {
        $this->withoutVite();
        Route::get('/_tests/ssr/{marker}', fn (string $marker) => Inertia::render('welcome', ['marker' => $marker]));
        $this->mock(Gateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('dispatch')->twice()->andReturnUsing(
                fn (array $page): Response => new Response('', '<div>'.e($page['props']['marker']).'</div>')
            );
        });

        $this->get('/_tests/ssr/first-request')->assertOk()->assertSee('first-request');
        $this->get('/_tests/ssr/second-request')->assertOk()->assertSee('second-request')->assertDontSee('first-request');
    }
}
