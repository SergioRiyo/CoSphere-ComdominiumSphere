<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'active', 'role:admin'])->get('/_tests/roles/admin', static fn () => response()->noContent());
        Route::middleware(['web', 'auth', 'active', 'role:morador'])->get('/_tests/roles/morador', static fn () => response()->noContent());
        Route::middleware(['web', 'auth', 'active', 'role:porteiro'])->get('/_tests/roles/porteiro', static fn () => response()->noContent());
        Route::middleware(['web', 'auth', 'active', 'role:admin,porteiro'])->get('/_tests/roles/admin-or-porteiro', static fn () => response()->noContent());
    }

    public function test_only_administrators_can_access_an_administrator_route(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/_tests/roles/admin')
            ->assertNoContent();

        $this->actingAs(User::factory()->morador()->create())
            ->get('/_tests/roles/admin')
            ->assertForbidden();

        $this->actingAs(User::factory()->porteiro()->create())
            ->get('/_tests/roles/admin')
            ->assertForbidden();
    }

    public function test_residents_and_doormen_can_access_routes_for_their_own_roles(): void
    {
        $this->actingAs(User::factory()->morador()->create())
            ->get('/_tests/roles/morador')
            ->assertNoContent();

        $this->actingAs(User::factory()->porteiro()->create())
            ->get('/_tests/roles/porteiro')
            ->assertNoContent();
    }

    public function test_routes_can_allow_multiple_roles(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/_tests/roles/admin-or-porteiro')
            ->assertNoContent();

        $this->actingAs(User::factory()->porteiro()->create())
            ->get('/_tests/roles/admin-or-porteiro')
            ->assertNoContent();

        $this->actingAs(User::factory()->morador()->create())
            ->get('/_tests/roles/admin-or-porteiro')
            ->assertForbidden();
    }
}
