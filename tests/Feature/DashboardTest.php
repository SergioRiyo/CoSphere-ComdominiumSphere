<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;
use App\Services\AdminDashboardService;
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

    public function test_administrative_dashboard_displays_current_user_and_unit_metrics(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->inactive()->create();

        $firstUnit = Unit::factory()->create();
        $secondUnit = Unit::factory()->create();

        User::factory()->morador()->active()->for($firstUnit)->create();
        User::factory()->morador()->inactive()->for($secondUnit)->create();
        User::factory()->porteiro()->active()->create();
        User::factory()->porteiro()->inactive()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/dashboard')
                ->where('metrics.active_users', 3)
                ->where('metrics.inactive_users', 3)
                ->where('metrics.administrators', 2)
                ->where('metrics.residents', 2)
                ->where('metrics.doormen', 2)
                ->where('metrics.units', 2));
    }

    public function test_administrative_dashboard_metrics_are_zero_without_records(): void
    {
        $this->assertSame([
            'active_users' => 0,
            'inactive_users' => 0,
            'administrators' => 0,
            'residents' => 0,
            'doormen' => 0,
            'units' => 0,
        ], app(AdminDashboardService::class)->metrics());
    }

    public function test_a_resident_can_access_the_resident_dashboard_with_their_own_unit(): void
    {
        $unit = Unit::factory()->create([
            'block' => 'Bloco B',
            'number' => '101',
            'type' => 'Apartamento',
            'complement' => 'Fundos',
        ]);
        $resident = User::factory()->morador()->for($unit)->create([
            'name' => 'Marina da Silva',
            'email' => 'marina@example.com',
        ]);

        $this->actingAs($resident)
            ->get(route('morador.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('morador/dashboard')
                ->where('auth.user.name', 'Marina da Silva')
                ->where('auth.user.email', 'marina@example.com')
                ->where('auth.user.role', UserRole::Morador->value)
                ->where('unit.id', $unit->id)
                ->where('unit.block', 'Bloco B')
                ->where('unit.number', '101')
                ->where('unit.type', 'Apartamento')
                ->where('unit.complement', 'Fundos'));
    }

    public function test_a_resident_without_a_unit_receives_a_null_unit(): void
    {
        $resident = User::factory()->morador()->create([
            'unit_id' => null,
            'name' => 'João da Costa',
            'email' => 'joao@example.com',
        ]);

        $this->actingAs($resident)
            ->get(route('morador.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('morador/dashboard')
                ->where('auth.user.name', 'João da Costa')
                ->where('auth.user.email', 'joao@example.com')
                ->where('auth.user.role', UserRole::Morador->value)
                ->where('unit', null));
    }

    public function test_a_doorman_can_access_the_portaria_dashboard(): void
    {
        $doorman = User::factory()->porteiro()->create([
            'name' => 'Carlos Souza',
            'email' => 'carlos@example.com',
        ]);

        $this->actingAs($doorman)
            ->get(route('portaria.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('portaria/dashboard')
                ->where('auth.user.name', 'Carlos Souza')
                ->where('auth.user.email', 'carlos@example.com')
                ->where('auth.user.role', UserRole::Porteiro->value)
                ->missing('visitors')
                ->missing('accesses')
                ->missing('orders'));
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
