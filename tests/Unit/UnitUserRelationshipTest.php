<?php

namespace Tests\Unit;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class UnitUserRelationshipTest extends TestCase
{
    public function test_unit_has_many_users(): void
    {
        $relation = (new Unit)->users();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(User::class, $relation->getRelated());
        $this->assertSame('unit_id', $relation->getForeignKeyName());
    }

    public function test_user_belongs_to_unit(): void
    {
        $relation = (new User)->unit();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(Unit::class, $relation->getRelated());
        $this->assertSame('unit_id', $relation->getForeignKeyName());
        $this->assertSame('id', $relation->getOwnerKeyName());
    }
}
