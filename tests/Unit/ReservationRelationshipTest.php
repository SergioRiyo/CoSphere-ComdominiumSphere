<?php

namespace Tests\Unit;

use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class ReservationRelationshipTest extends TestCase
{
    public function test_common_area_has_many_reservations(): void
    {
        $relation = (new CommonArea)->reservations();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(Reservation::class, $relation->getRelated());
        $this->assertSame('common_area_id', $relation->getForeignKeyName());
    }

    public function test_reservation_belongs_to_common_area(): void
    {
        $relation = (new Reservation)->commonArea();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(CommonArea::class, $relation->getRelated());
        $this->assertSame('common_area_id', $relation->getForeignKeyName());
        $this->assertSame('id', $relation->getOwnerKeyName());
    }

    public function test_reservation_belongs_to_user(): void
    {
        $relation = (new Reservation)->user();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(User::class, $relation->getRelated());
        $this->assertSame('user_id', $relation->getForeignKeyName());
        $this->assertSame('id', $relation->getOwnerKeyName());
    }

    public function test_user_has_many_reservations(): void
    {
        $relation = (new User)->reservations();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(Reservation::class, $relation->getRelated());
        $this->assertSame('user_id', $relation->getForeignKeyName());
    }
}
