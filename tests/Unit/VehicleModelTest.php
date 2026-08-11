<?php

namespace Tests\Unit;

use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_factory_persists_base_attributes_and_relationships(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);

        $vehicle = Vehicle::factory()
            ->for($unit)
            ->for($user)
            ->create();

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'unit_id' => $unit->id,
            'user_id' => $user->id,
            'plate' => $vehicle->plate,
            'model' => $vehicle->model,
            'color' => $vehicle->color,
        ]);
        $this->assertTrue($vehicle->unit->is($unit));
        $this->assertTrue($vehicle->user->is($user));
    }

    public function test_vehicle_belongs_to_a_unit_and_user(): void
    {
        $this->assertInstanceOf(BelongsTo::class, (new Vehicle)->unit());
        $this->assertInstanceOf(BelongsTo::class, (new Vehicle)->user());
    }
}
