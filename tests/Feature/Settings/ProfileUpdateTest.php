<?php

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_displays_editable_and_read_only_information(): void
    {
        $unit = Unit::factory()->create([
            'block' => 'A',
            'number' => '101',
            'type' => 'apartamento',
            'complement' => 'Fundos',
        ]);
        $user = User::factory()->morador()->for($unit)->create([
            'name' => 'Marina da Silva',
            'email' => 'marina@example.com',
            'cpf' => '123.456.789-00',
            'phone' => '(65) 99999-0000',
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/profile')
                ->where('auth.user.name', 'Marina da Silva')
                ->where('auth.user.email', 'marina@example.com')
                ->where('auth.user.phone', '(65) 99999-0000')
                ->where('auth.user.cpf', '123.456.789-00')
                ->where('auth.user.role', UserRole::Morador->value)
                ->where('auth.user.is_active', true)
                ->where('roleLabel', UserRole::Morador->label())
                ->where('unit.id', $unit->id)
                ->where('unit.block', 'A')
                ->where('unit.number', '101')
                ->where('unit.type', 'apartamento')
                ->where('unit.complement', 'Fundos'));
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'phone' => '(65) 98888-7777',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertSame('(65) 98888-7777', $user->phone);
        $this->assertNull($user->email_verified_at);
    }

    public function test_phone_can_be_cleared(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => '',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNull($user->refresh()->phone);
    }

    public function test_phone_may_not_exceed_thirty_characters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => str_repeat('1', 31),
            ])
            ->assertSessionHasErrors('phone')
            ->assertRedirect(route('profile.edit'));
    }

    public function test_email_must_be_unique_except_for_the_current_user(): void
    {
        $existingUser = User::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $existingUser->email,
                'phone' => $user->phone,
            ])
            ->assertSessionHasErrors('email')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotSame($existingUser->email, $user->refresh()->email);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
                'phone' => $user->phone,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_read_only_profile_fields_cannot_be_changed_by_the_profile_endpoint(): void
    {
        $currentUnit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $user = User::factory()->morador()->for($currentUnit)->create([
            'cpf' => '123.456.789-00',
        ]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Nome atualizado',
                'email' => $user->email,
                'phone' => '(65) 99999-9999',
                'cpf' => '987.654.321-00',
                'role' => UserRole::Admin->value,
                'is_active' => false,
                'unit_id' => $otherUnit->id,
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('123.456.789-00', $user->cpf);
        $this->assertSame(UserRole::Morador, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame($currentUnit->id, $user->unit_id);
    }

    public function test_self_deletion_endpoint_is_not_available(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete('/settings/profile', ['password' => 'password'])
            ->assertMethodNotAllowed();

        $this->assertModelExists($user);
    }

    public function test_guests_cannot_access_profile_settings(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->patch(route('profile.update'))->assertRedirect(route('login'));
    }

    public function test_inactive_users_cannot_access_profile_settings(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
