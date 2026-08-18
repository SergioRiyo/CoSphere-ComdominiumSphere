<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DataIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_a_receives_only_unit_a_on_their_dashboard(): void
    {
        [$unitA, $residentA] = $this->createResidentsForDifferentUnits();

        $this->actingAs($residentA)
            ->get(route('morador.dashboard'))
            ->assertInertia(fn (Assert $page) => $this->assertResidentDashboardUnit($page, $unitA));
    }

    public function test_resident_b_receives_only_unit_b_on_their_dashboard(): void
    {
        [, , $unitB, $residentB] = $this->createResidentsForDifferentUnits();

        $this->actingAs($residentB)
            ->get(route('morador.dashboard'))
            ->assertInertia(fn (Assert $page) => $this->assertResidentDashboardUnit($page, $unitB));
    }

    public function test_query_string_ids_do_not_change_the_resident_dashboard_unit(): void
    {
        [$unitA, $residentA, $unitB, $residentB] = $this->createResidentsForDifferentUnits();

        $this->actingAs($residentA)
            ->get(route('morador.dashboard', [
                'unit_id' => $unitB->id,
                'user_id' => $residentB->id,
                'resident_id' => $residentB->id,
            ]))
            ->assertInertia(fn (Assert $page) => $this->assertResidentDashboardUnit($page, $unitA));
    }

    public function test_resident_without_a_unit_receives_no_fallback_unit(): void
    {
        [, , $unitB] = $this->createResidentsForDifferentUnits();
        $residentWithoutUnit = User::factory()->morador()->create([
            'unit_id' => null,
        ]);

        $this->actingAs($residentWithoutUnit)
            ->get(route('morador.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('morador/dashboard')
                ->where('unit', null)
                ->missing('unit.id')
                ->missing('unit.block')
                ->missing('unit.number')
                ->where('auth.user.id', $residentWithoutUnit->id)
                ->where('auth.user.role', UserRole::Morador->value));

        $this->assertModelExists($unitB);
    }

    /**
     * @return array{0: Unit, 1: User, 2: Unit, 3: User}
     */
    private function createResidentsForDifferentUnits(): array
    {
        $unitA = Unit::factory()->create([
            'block' => 'Bloco Alfa',
            'number' => 'A-101',
            'type' => 'Casa',
            'complement' => 'Fundos A',
        ]);

        $unitB = Unit::factory()->create([
            'block' => 'Bloco Beta',
            'number' => 'B-202',
            'type' => 'Apartamento',
            'complement' => 'Cobertura B',
        ]);

        $residentA = User::factory()->morador()->create([
            'unit_id' => $unitA->id,
            'name' => 'Morador da Unidade A',
            'email' => 'morador.a@example.com',
        ]);

        $residentB = User::factory()->morador()->create([
            'unit_id' => $unitB->id,
            'name' => 'Morador da Unidade B',
            'email' => 'morador.b@example.com',
        ]);

        return [$unitA, $residentA, $unitB, $residentB];
    }

    private function assertResidentDashboardUnit(Assert $page, Unit $unit): Assert
    {
        return $page
            ->component('morador/dashboard')
            ->where('unit.id', $unit->id)
            ->where('unit.block', $unit->block)
            ->where('unit.number', $unit->number)
            ->where('unit.type', $unit->type)
            ->where('unit.complement', $unit->complement);
    }
}
