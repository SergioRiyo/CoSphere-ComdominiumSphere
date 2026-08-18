<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_is_cast_to_user_role(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->assertSame($role, $user->fresh()->role);
        }
    }

    public function test_new_users_are_active_by_default_and_is_active_is_a_boolean(): void
    {
        $activeUser = User::factory()->create();
        $inactiveUser = User::factory()->inactive()->create();

        $this->assertTrue($activeUser->fresh()->is_active);
        $this->assertFalse($inactiveUser->fresh()->is_active);
    }

    public function test_factory_creates_valid_users_for_each_role(): void
    {
        $morador = User::factory()->morador()->create();
        $admin = User::factory()->admin()->create();
        $porteiro = User::factory()->porteiro()->create();

        $this->assertNotNull($morador->unit_id);
        $this->assertNull($admin->unit_id);
        $this->assertNull($porteiro->unit_id);
        $this->assertSame(UserRole::Morador, $morador->role);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertSame(UserRole::Porteiro, $porteiro->role);
    }
}
