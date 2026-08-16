<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        foreach (['dashboard', 'admin.dashboard', 'morador.dashboard', 'portaria.dashboard'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_unverified_users_are_redirected_to_the_email_verification_notice(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_users_cannot_access_role_dashboards_directly(): void
    {
        $dashboards = [
            [User::factory()->admin()->unverified()->create(), 'admin.dashboard'],
            [User::factory()->morador()->unverified()->create(), 'morador.dashboard'],
            [User::factory()->porteiro()->unverified()->create(), 'portaria.dashboard'],
        ];

        foreach ($dashboards as [$user, $route]) {
            $this->actingAs($user)
                ->get(route($route))
                ->assertRedirect(route('verification.notice'));
        }
    }

    public function test_dashboard_dispatches_an_administrator_to_the_administrative_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_dashboard_dispatches_a_resident_to_the_resident_dashboard(): void
    {
        $this->actingAs(User::factory()->morador()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('morador.dashboard'));
    }

    public function test_dashboard_dispatches_a_doorman_to_the_portaria_dashboard(): void
    {
        $this->actingAs(User::factory()->porteiro()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('portaria.dashboard'));
    }

    public function test_an_administrator_can_access_the_administrative_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/dashboard'));
    }

    public function test_a_resident_can_access_the_resident_dashboard_with_their_own_unit(): void
    {
        $unit = Unit::factory()->create([
            'number' => '101',
            'type' => 'Apartamento',
            'complement' => 'Bloco A',
        ]);
        $resident = User::factory()->morador()->for($unit)->create();

        $this->actingAs($resident)
            ->get(route('morador.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('morador/dashboard')
                ->where('unit.number', '101')
                ->where('unit.type', 'Apartamento')
                ->where('unit.complement', 'Bloco A'));
    }

    public function test_a_doorman_can_access_the_portaria_dashboard(): void
    {
        $this->actingAs(User::factory()->porteiro()->create())
            ->get(route('portaria.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->component('portaria/dashboard'));
    }

    public function test_inertia_shares_the_authenticated_user_role(): void
    {
        $dashboards = [
            [User::factory()->admin()->create(), 'admin.dashboard', UserRole::Admin->value],
            [User::factory()->morador()->create(), 'morador.dashboard', UserRole::Morador->value],
            [User::factory()->porteiro()->create(), 'portaria.dashboard', UserRole::Porteiro->value],
        ];

        foreach ($dashboards as [$user, $route, $role]) {
            $this->actingAs($user)
                ->get(route($route))
                ->assertInertia(fn (Assert $page) => $page->where('auth.user.role', $role));
        }
    }

    public function test_users_cannot_access_dashboards_for_other_roles(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $doorman = User::factory()->porteiro()->create();

        $this->actingAs($resident)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($doorman)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('morador.dashboard'))->assertForbidden();
        $this->actingAs($doorman)->get(route('morador.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('portaria.dashboard'))->assertForbidden();
        $this->actingAs($resident)->get(route('portaria.dashboard'))->assertForbidden();
    }

    public function test_an_inactive_user_is_blocked_from_a_role_dashboard(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $user->update(['is_active' => false]);

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
