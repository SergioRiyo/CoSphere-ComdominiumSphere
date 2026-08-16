<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_common_area_factory_makes_common_area(): void
    {
        $this->assertInstanceOf(CommonArea::class, CommonArea::factory()->make());
    }

    public function test_reservation_factory_makes_reservation(): void
    {
        $reservation = Reservation::factory()->make([
            'common_area_id' => 1,
            'user_id' => 1,
            'unit_id' => 1,
        ]);

        $this->assertInstanceOf(Reservation::class, $reservation);
    }

    public function test_unit_factory_makes_unit(): void
    {
        $this->assertInstanceOf(Unit::class, Unit::factory()->make());
    }

    public function test_user_factory_uses_default_resident_role_with_a_unit(): void
    {
        $user = User::factory()->make();

        $this->assertSame(UserRole::Morador, $user->role);
        $this->assertNotNull($user->unit_id);
    }
}
