<?php

namespace Tests\Unit;

use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\Unit;
use App\Models\User;
use Tests\TestCase;

class ModelFactoryTest extends TestCase
{
    public function test_common_area_factory_makes_common_area(): void
    {
        $this->assertInstanceOf(CommonArea::class, CommonArea::factory()->make());
    }

    public function test_reservation_factory_makes_reservation(): void
    {
        $this->assertInstanceOf(Reservation::class, Reservation::factory()->make());
    }

    public function test_unit_factory_makes_unit(): void
    {
        $this->assertInstanceOf(Unit::class, Unit::factory()->make());
    }

    public function test_user_factory_uses_default_resident_role(): void
    {
        $user = User::factory()->make();

        $this->assertSame('morador', $user->role);
        $this->assertNull($user->unit_id);
    }
}
