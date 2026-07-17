<?php

namespace Tests\Unit;

use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_a_unit_with_required_type(): void
    {
        $unit = Unit::factory()->create();

        $this->assertNotNull($unit->type);
        $this->assertNotSame('', $unit->type);
    }
}
