<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ValueError;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_profile_fields_are_persisted_and_cast(): void
    {
        $user = User::factory()->inactive()->create([
            'cpf' => '529.982.247-25',
            'phone' => '(65) 99999-9999',
        ])->refresh();

        $this->assertSame('529.982.247-25', $user->cpf);
        $this->assertSame('(65) 99999-9999', $user->phone);
        $this->assertFalse($user->is_active);
    }

    public function test_resident_is_linked_to_a_unit(): void
    {
        $unit = Unit::factory()->create();
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);

        $this->assertTrue($resident->unit->is($unit));
        $this->assertTrue($unit->users()->whereKey($resident)->exists());
    }

    public function test_admin_and_doorman_can_exist_without_a_unit(): void
    {
        $admin = User::factory()->admin()->create();
        $doorman = User::factory()->porteiro()->create();

        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertNull($admin->unit_id);
        $this->assertSame(UserRole::Porteiro, $doorman->role);
        $this->assertNull($doorman->unit_id);
    }

    public function test_user_is_active_by_default(): void
    {
        $user = User::query()->create([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'role' => UserRole::Admin,
            'password' => 'password',
        ]);

        $this->assertTrue($user->is_active);
        $this->assertNull($user->cpf);
        $this->assertNull($user->phone);
    }

    public function test_users_table_has_an_index_for_the_unit_relationship(): void
    {
        $index = collect(Schema::getIndexes('users'))
            ->firstWhere('name', 'users_unit_id_index');

        $this->assertNotNull($index);
        $this->assertSame(['unit_id'], $index['columns']);
    }

    public function test_unsupported_user_role_is_rejected(): void
    {
        $this->expectException(ValueError::class);

        User::factory()->create(['role' => 'sindico']);
    }

    public function test_cpf_must_be_unique_when_present(): void
    {
        User::factory()->create(['cpf' => '529.982.247-25']);

        $this->expectException(QueryException::class);

        User::factory()->create(['cpf' => '529.982.247-25']);
    }
}
