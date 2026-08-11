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

    public function test_factory_allows_the_optional_block_to_be_persisted(): void
    {
        $unit = Unit::factory()->create(['block' => 'Bloco A']);

        $this->assertSame('Bloco A', $unit->block);
    }
}
