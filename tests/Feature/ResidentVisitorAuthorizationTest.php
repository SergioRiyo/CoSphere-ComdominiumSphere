<?php

namespace Tests\Feature;

use App\Enums\VisitorAuthorizationStatus;
use App\Models\Unit;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentVisitorAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_creates_an_active_authorization_for_their_unit(): void
    {
        $unit = Unit::factory()->create(['status' => 'active']);
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);

        $this->actingAs($resident)
            ->from(route('morador.visitors.index'))
            ->post(route('morador.visitors.store'), $this->validData())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('morador.visitors.index'));

        $visitor = Visitor::query()->sole();
        $authorization = VisitorAuthorization::query()->sole();

        $this->assertSame('Maria Visitante', $visitor->name);
        $this->assertSame('529.982.247-25', $visitor->cpf);
        $this->assertSame('(65) 99999-9999', $visitor->phone);
        $this->assertSame('BRA-2E19', $authorization->vehicle_plate);
        $this->assertSame($resident->id, $authorization->resident_id);
        $this->assertSame($unit->id, $authorization->unit_id);
        $this->assertSame(VisitorAuthorizationStatus::Active, $authorization->status);
        $this->assertNotEmpty($authorization->access_code);
        $this->assertNotNull($authorization->authorized_date);
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $resident->id,
            'title' => 'Visitante autorizado',
        ]);
    }

    public function test_request_ownership_fields_are_ignored(): void
    {
        $unit = Unit::factory()->create(['status' => 'active']);
        $otherUnit = Unit::factory()->create(['status' => 'active']);
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $otherResident = User::factory()->morador()->create(['unit_id' => $otherUnit->id]);

        $this->actingAs($resident)
            ->post(route('morador.visitors.store'), $this->validData([
                'unit_id' => $otherUnit->id,
                'resident_id' => $otherResident->id,
                'status' => VisitorAuthorizationStatus::Canceled->value,
                'access_code' => 'client-controlled-code',
            ]))
            ->assertSessionHasNoErrors();

        $authorization = VisitorAuthorization::query()->sole();

        $this->assertSame($unit->id, $authorization->unit_id);
        $this->assertSame($resident->id, $authorization->resident_id);
        $this->assertSame(VisitorAuthorizationStatus::Active, $authorization->status);
        $this->assertNotSame('client-controlled-code', $authorization->access_code);
    }

    public function test_existing_visitor_is_reused_by_normalized_cpf(): void
    {
        $unit = Unit::factory()->create(['status' => 'active']);
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $visitor = Visitor::factory()->create([
            'name' => 'Nome anterior',
            'cpf' => '529.982.247-25',
            'phone' => '(65) 98888-7777',
        ]);

        $this->actingAs($resident)
            ->post(route('morador.visitors.store'), $this->validData())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('visitors', 1);
        $this->assertSame($visitor->id, VisitorAuthorization::query()->sole()->visitor_id);
        $this->assertSame('Nome anterior', $visitor->refresh()->name);
        $this->assertSame('(65) 98888-7777', $visitor->phone);
    }

    public function test_reusing_a_visitor_does_not_change_data_from_another_unit(): void
    {
        $unit = Unit::factory()->create(['status' => 'active']);
        $otherUnit = Unit::factory()->create(['status' => 'active']);
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $otherResident = User::factory()->morador()->create(['unit_id' => $otherUnit->id]);
        $visitor = Visitor::factory()->create([
            'name' => 'Visitante Compartilhado',
            'cpf' => '529.982.247-25',
            'phone' => '(65) 98888-7777',
        ]);
        VisitorAuthorization::factory()->active()->create([
            'visitor_id' => $visitor->id,
            'unit_id' => $otherUnit->id,
            'resident_id' => $otherResident->id,
        ]);

        $this->actingAs($resident)
            ->post(route('morador.visitors.store'), $this->validData())
            ->assertSessionHasNoErrors();

        $this->assertSame('Visitante Compartilhado', $visitor->refresh()->name);
        $this->assertSame('(65) 98888-7777', $visitor->phone);
        $this->assertDatabaseCount('visitor_authorizations', 2);
    }

    public function test_trashed_visitor_is_restored_and_reused_by_cpf(): void
    {
        $unit = Unit::factory()->create(['status' => 'active']);
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $visitor = Visitor::factory()->create(['cpf' => '529.982.247-25']);
        $visitor->delete();

        $this->actingAs($resident)
            ->post(route('morador.visitors.store'), $this->validData())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('visitors', 1);
        $this->assertSame($visitor->id, VisitorAuthorization::query()->sole()->visitor_id);
        $this->assertNull($visitor->refresh()->deleted_at);
    }

    public function test_invalid_data_and_invalid_periods_are_not_persisted(): void
    {
        $resident = User::factory()->morador()->create();

        $this->actingAs($resident)
            ->post(route('morador.visitors.store'), [
                'name' => '',
                'cpf' => 'invalid',
                'phone' => 'invalid',
                'vehicle_plate' => 'invalid',
                'start_date' => now()->subMinute()->toDateTimeString(),
                'end_date' => now()->subHours(2)->toDateTimeString(),
            ])
            ->assertSessionHasErrors([
                'name',
                'cpf',
                'phone',
                'vehicle_plate',
                'start_date',
                'end_date',
            ]);

        $this->assertDatabaseCount('visitors', 0);
        $this->assertDatabaseCount('visitor_authorizations', 0);
    }

    public function test_only_residents_with_an_active_unit_can_create_authorizations(): void
    {
        $route = route('morador.visitors.store');

        $this->post($route, $this->validData())->assertRedirect(route('login'));

        $this->actingAs(User::factory()->admin()->create())
            ->post($route, $this->validData())
            ->assertForbidden();

        $this->actingAs(User::factory()->porteiro()->create())
            ->post($route, $this->validData())
            ->assertForbidden();

        $this->actingAs(User::factory()->morador()->create(['unit_id' => null]))
            ->post($route, $this->validData())
            ->assertForbidden();

        $inactiveUnit = Unit::factory()->create(['status' => 'inactive']);
        $this->actingAs(User::factory()->morador()->create(['unit_id' => $inactiveUnit->id]))
            ->post($route, $this->validData())
            ->assertForbidden();

        $activeUnit = Unit::factory()->create(['status' => 'active']);
        $this->actingAs(User::factory()->morador()->inactive()->create(['unit_id' => $activeUnit->id]))
            ->post($route, $this->validData())
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('visitor_authorizations', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
    {
        return [
            'name' => '  Maria Visitante  ',
            'cpf' => '52998224725',
            'phone' => '65999999999',
            'vehicle_plate' => ' bra2e19 ',
            'start_date' => now()->addHour()->toDateTimeString(),
            'end_date' => now()->addHours(2)->toDateTimeString(),
            ...$overrides,
        ];
    }
}
