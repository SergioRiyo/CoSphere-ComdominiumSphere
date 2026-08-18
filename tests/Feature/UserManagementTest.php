<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_access_user_management(): void
    {
        $route = route('admin.users.index');

        $this->get($route)->assertRedirect(route('login'));

        $this->actingAs(User::factory()->morador()->create())
            ->get($route)
            ->assertForbidden();

        $this->actingAs(User::factory()->porteiro()->create())
            ->get($route)
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get($route)
            ->assertInertia(fn (Assert $page) => $page->component('admin/users'));
    }

    public function test_residents_and_doormen_cannot_mutate_user_management(): void
    {
        $managedUser = User::factory()->morador()->create();

        foreach ([
            User::factory()->morador()->create(),
            User::factory()->porteiro()->create(),
        ] as $user) {
            $this->actingAs($user)
                ->post(route('admin.users.store'))
                ->assertForbidden();

            $this->actingAs($user)
                ->patch(route('admin.users.update', $managedUser))
                ->assertForbidden();

            $this->actingAs($user)
                ->patch(route('admin.users.status.update', $managedUser))
                ->assertForbidden();
        }
    }

    public function test_users_are_searched_filtered_and_paginated_on_the_backend(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->morador()->create(['name' => 'Ana Filtrada']);
        User::factory()->morador()->inactive()->create(['name' => 'Ana Inativa']);
        User::factory()->porteiro()->count(11)->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index', [
                'search' => 'Ana Filtrada',
                'role' => UserRole::Morador->value,
                'status' => 'active',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users')
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Ana Filtrada')
                ->where('filters.search', 'Ana Filtrada')
                ->where('filters.role', UserRole::Morador->value)
                ->where('filters.status', 'active'));

        $this->actingAs($admin)
            ->get(route('admin.users.index', [
                'role' => UserRole::Porteiro->value,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 10)
                ->where('users.last_page', 2)
                ->where(
                    'users.next_page_url',
                    fn (?string $url): bool => $url !== null
                        && str_contains($url, 'role=porteiro'),
                ));
    }

    public function test_administrator_can_create_a_resident_with_a_unit(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = Unit::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.store'), $this->validUserData([
                'unit_id' => $unit->id,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $resident = User::query()->where('email', 'novo.usuario@example.com')->firstOrFail();

        $this->assertSame(UserRole::Morador, $resident->role);
        $this->assertTrue($resident->unit->is($unit));
        $this->assertTrue(Hash::check('password', $resident->password));
    }

    public function test_administrator_ignores_a_tampered_unit_when_creating_an_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = Unit::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.store'), $this->validUserData([
                'role' => UserRole::Admin->value,
                'unit_id' => $unit->id,
            ]))
            ->assertSessionHasNoErrors();

        $createdAdmin = User::query()->where('email', 'novo.usuario@example.com')->firstOrFail();

        $this->assertSame(UserRole::Admin, $createdAdmin->role);
        $this->assertNull($createdAdmin->unit_id);
    }

    public function test_administrator_ignores_a_tampered_unit_when_creating_a_doorman(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = Unit::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.store'), $this->validUserData([
                'role' => UserRole::Porteiro->value,
                'unit_id' => $unit->id,
            ]))
            ->assertSessionHasNoErrors();

        $doorman = User::query()->where('email', 'novo.usuario@example.com')->firstOrFail();

        $this->assertSame(UserRole::Porteiro, $doorman->role);
        $this->assertNull($doorman->unit_id);
    }

    public function test_resident_requires_a_unit_when_creating_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $data = $this->validUserData();

        unset($data['unit_id']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $data)
            ->assertSessionHasErrors('unit_id');
    }

    public function test_user_management_service_rejects_a_resident_without_a_unit(): void
    {
        $this->expectException(LogicException::class);

        app(UserManagementService::class)->create([
            'name' => 'Morador sem unidade',
            'email' => 'morador.sem.unidade@example.com',
            'cpf' => '529.982.247-25',
            'phone' => '(65) 99999-9999',
            'role' => UserRole::Morador->value,
            'password' => 'password',
        ]);
    }

    public function test_resident_requires_an_existing_unit_when_creating_a_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->validUserData([
                'unit_id' => 999999,
            ]))
            ->assertSessionHasErrors('unit_id');
    }

    public function test_profile_fields_must_be_unique_when_creating_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $existingUser = User::factory()->create([
            'email' => 'existente@example.com',
            'cpf' => '987.654.321-00',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->validUserData([
                'email' => $existingUser->email,
                'cpf' => $existingUser->cpf,
                'role' => 'sindico',
            ]))
            ->assertSessionHasErrors(['email', 'cpf', 'role']);
    }

    public function test_administrator_clears_a_unit_when_changing_a_resident_to_an_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $tamperedUnit = Unit::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.update', $resident), $this->validUserUpdateData($resident, [
                'role' => UserRole::Admin->value,
                'unit_id' => $tamperedUnit->id,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $resident->refresh();

        $this->assertSame(UserRole::Admin, $resident->role);
        $this->assertNull($resident->unit_id);
    }

    public function test_administrator_clears_a_unit_when_changing_a_resident_to_a_doorman(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $tamperedUnit = Unit::factory()->create();
        $originalPassword = $resident->password;

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.update', $resident), $this->validUserUpdateData($resident, [
                'name' => 'Porteiro Atualizado',
                'phone' => '(65) 98888-7777',
                'role' => UserRole::Porteiro->value,
                'unit_id' => $tamperedUnit->id,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $resident->refresh();

        $this->assertSame('Porteiro Atualizado', $resident->name);
        $this->assertSame(UserRole::Porteiro, $resident->role);
        $this->assertNull($resident->unit_id);
        $this->assertSame($originalPassword, $resident->password);
    }

    public function test_administrator_requires_a_unit_when_changing_an_admin_to_a_resident(): void
    {
        $administrator = User::factory()->admin()->create();
        $managedAdmin = User::factory()->admin()->create();

        $this->actingAs($administrator)
            ->patch(route('admin.users.update', $managedAdmin), $this->validUserUpdateData($managedAdmin, [
                'role' => UserRole::Morador->value,
                'unit_id' => null,
            ]))
            ->assertSessionHasErrors('unit_id');

        $this->assertSame(UserRole::Admin, $managedAdmin->refresh()->role);
        $this->assertNull($managedAdmin->unit_id);
    }

    public function test_administrator_requires_an_existing_unit_when_changing_an_admin_to_a_resident(): void
    {
        $administrator = User::factory()->admin()->create();
        $managedAdmin = User::factory()->admin()->create();

        $this->actingAs($administrator)
            ->patch(route('admin.users.update', $managedAdmin), $this->validUserUpdateData($managedAdmin, [
                'role' => UserRole::Morador->value,
                'unit_id' => 999999,
            ]))
            ->assertSessionHasErrors('unit_id');

        $this->assertSame(UserRole::Admin, $managedAdmin->refresh()->role);
        $this->assertNull($managedAdmin->unit_id);
    }

    public function test_administrator_can_change_an_admin_to_a_resident_with_an_existing_unit(): void
    {
        $administrator = User::factory()->admin()->create();
        $managedAdmin = User::factory()->admin()->create();
        $unit = Unit::factory()->create();

        $this->actingAs($administrator)
            ->patch(route('admin.users.update', $managedAdmin), $this->validUserUpdateData($managedAdmin, [
                'role' => UserRole::Morador->value,
                'unit_id' => $unit->id,
            ]))
            ->assertSessionHasNoErrors();

        $managedAdmin->refresh();

        $this->assertSame(UserRole::Morador, $managedAdmin->role);
        $this->assertTrue($managedAdmin->unit->is($unit));
    }

    public function test_administrator_requires_a_unit_when_changing_a_doorman_to_a_resident(): void
    {
        $administrator = User::factory()->admin()->create();
        $doorman = User::factory()->porteiro()->create();

        $this->actingAs($administrator)
            ->patch(route('admin.users.update', $doorman), $this->validUserUpdateData($doorman, [
                'role' => UserRole::Morador->value,
                'unit_id' => null,
            ]))
            ->assertSessionHasErrors('unit_id');

        $this->assertSame(UserRole::Porteiro, $doorman->refresh()->role);
        $this->assertNull($doorman->unit_id);
    }

    public function test_administrator_can_change_a_doorman_to_a_resident_with_an_existing_unit(): void
    {
        $administrator = User::factory()->admin()->create();
        $doorman = User::factory()->porteiro()->create();
        $unit = Unit::factory()->create();

        $this->actingAs($administrator)
            ->patch(route('admin.users.update', $doorman), $this->validUserUpdateData($doorman, [
                'role' => UserRole::Morador->value,
                'unit_id' => $unit->id,
            ]))
            ->assertSessionHasNoErrors();

        $doorman->refresh();

        $this->assertSame(UserRole::Morador, $doorman->role);
        $this->assertTrue($doorman->unit->is($unit));
    }

    public function test_administrator_can_inactivate_and_activate_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->porteiro()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.status.update', $user), [
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($user->refresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('admin.users.status.update', $user), [
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->refresh()->is_active);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validUserData(array $overrides = []): array
    {
        return [
            'name' => 'Novo Usuário',
            'email' => 'novo.usuario@example.com',
            'cpf' => '529.982.247-25',
            'phone' => '(65) 99999-9999',
            'role' => UserRole::Morador->value,
            'unit_id' => Unit::factory()->create()->id,
            'password' => 'password',
            'password_confirmation' => 'password',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validUserUpdateData(User $user, array $overrides = []): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'cpf' => $user->cpf,
            'phone' => $user->phone,
            'role' => $user->role->value,
            'unit_id' => $user->unit_id,
            ...$overrides,
        ];
    }
}
