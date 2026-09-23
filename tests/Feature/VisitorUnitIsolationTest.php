<?php

namespace Tests\Feature;

use App\Enums\VisitorAuthorizationStatus;
use App\Models\Unit;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VisitorUnitIsolationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('creationFlows')]
    public function test_same_cpf_is_isolated_through_resident_views_and_the_portaria_lifecycle(bool $invitation): void
    {
        $this->travelTo(now()->startOfMinute());
        $residentA = User::factory()->morador()->create();
        $residentB = User::factory()->morador()->create();
        $authorizationA = $this->createDirect($residentA, 'Visitante Unidade Alfa', '65111111111');
        $authorizationB = $invitation
            ? $this->completeInvitation($residentB, 'Visitante Unidade Beta', '65222222222', $residentA->unit_id)
            : $this->createDirect($residentB, 'Visitante Unidade Beta', '65222222222', [
                'unit_id' => $residentA->unit_id,
                'resident_id' => $residentA->id,
                'visitor_id' => $authorizationA->visitor_id,
            ]);

        $this->assertDatabaseCount('visitors', 2);
        $this->assertNotSame($authorizationA->visitor_id, $authorizationB->visitor_id);
        $this->assertSame('529.982.247-25', $authorizationA->visitor->cpf);
        $this->assertSame($authorizationA->visitor->cpf, $authorizationB->visitor->cpf);

        $contexts = [
            [$residentA, $authorizationA, $authorizationB, 'Visitante Unidade Alfa', '(65) 11111-1111'],
            [$residentB, $authorizationB, $authorizationA, 'Visitante Unidade Beta', '(65) 22222-2222'],
        ];

        foreach ($contexts as [$resident, $authorization, $other, $name, $phone]) {
            $this->assertSame($resident->unit_id, $authorization->visitor->unit_id);
            $this->assertSame($name, $authorization->visitor->name);
            $this->assertSame($phone, $authorization->visitor->phone);
            $this->assertResidentViews($resident, $authorization, $other, $name, $phone);

            $this->actingAs($resident)->get(route('morador.visitors.access-code', $authorization))
                ->assertOk()->assertContent($authorization->access_code);
            $this->get(route('morador.visitors.qr-code', $authorization))->assertOk();
        }

        $this->travel(2)->hours();
        $doorman = User::factory()->porteiro()->create();

        foreach ($contexts as [$resident, $authorization, $other, $name, $phone]) {
            $this->actingAs($doorman)
                ->postJson(route('portaria.visitor-authorizations.validate'), ['access_code' => $authorization->access_code])
                ->assertOk()
                ->assertJsonPath('allowed', true)
                ->assertJsonPath('authorization.visitor_name', $name)
                ->assertJsonPath('authorization.unit.number', $resident->unit->number);

            $this->postJson(route('portaria.visitor-accesses.store'), ['access_code' => $authorization->access_code])
                ->assertCreated()->assertJsonPath('registered', true);
            $access = VisitorAccess::where('visitor_authorization_id', $authorization->id)->sole();

            $this->get(route('portaria.visitor-accesses.index'))
                ->assertInertia(fn (Assert $page) => $page
                    ->has('openAccesses', 1)
                    ->where('openAccesses.0.visitor_name', $name)
                    ->where('openAccesses.0.unit.number', $resident->unit->number));

            $this->post(route('portaria.visitor-accesses.exit', $access))->assertRedirect();
            $this->assertNotNull($access->refresh()->exit_time);
            $this->assertSame(VisitorAuthorizationStatus::Used, $authorization->refresh()->status);

            $this->get(route('portaria.visitor-access-history.index', ['unit_id' => $resident->unit_id]))
                ->assertInertia(fn (Assert $page) => $page
                    ->has('accesses.data', 1)
                    ->where('accesses.data.0.visitor_name', $name)
                    ->where('accesses.data.0.unit.number', $resident->unit->number)
                    ->where('accesses.data.0.situation', 'finished'));

            $this->assertResidentViews($resident, $authorization, $other, $name, $phone);
        }
    }

    public function test_invitation_reuses_only_the_same_units_normalized_cpf_without_overwriting_pii(): void
    {
        $resident = User::factory()->morador()->create();
        $direct = $this->createDirect($resident, 'Nome Preservado', '65111111111');
        $invited = $this->completeInvitation($resident, 'Nome Diferente', '65222222222', Unit::factory()->create()->id);

        $this->assertDatabaseCount('visitors', 1);
        $this->assertSame($direct->visitor_id, $invited->visitor_id);
        $this->assertSame('Nome Preservado', $invited->visitor->name);
        $this->assertSame('(65) 11111-1111', $invited->visitor->phone);
    }

    public function test_another_units_soft_deleted_visitor_is_not_restored_or_reused(): void
    {
        $visitor = Visitor::factory()->create(['cpf' => '529.982.247-25']);
        $visitor->delete();
        $resident = User::factory()->morador()->create();

        $authorization = $this->createDirect($resident, 'Visitante Independente', '65222222222');

        $this->assertNotSame($visitor->id, $authorization->visitor_id);
        $this->assertSoftDeleted($visitor);
        $this->assertDatabaseCount('visitors', 2);
        $this->assertSame('Visitante Independente', $authorization->visitor->name);
    }

    public function test_changing_one_units_visitor_does_not_change_the_other_units_pii(): void
    {
        $residentA = User::factory()->morador()->create();
        $residentB = User::factory()->morador()->create();
        $authorizationA = $this->createDirect($residentA, 'Visitante Alfa', '65111111111');
        $authorizationB = $this->createDirect($residentB, 'Visitante Beta', '65222222222');

        $authorizationB->visitor->update(['name' => 'Beta Atualizado', 'phone' => '(65) 33333-3333']);

        $this->assertSame('Visitante Alfa', $authorizationA->visitor->refresh()->name);
        $this->assertSame('(65) 11111-1111', $authorizationA->visitor->phone);
        $this->assertResidentViews($residentA, $authorizationA, $authorizationB, 'Visitante Alfa', '(65) 11111-1111');
        $this->assertResidentViews($residentB, $authorizationB, $authorizationA, 'Beta Atualizado', '(65) 33333-3333');
    }

    public function test_visitor_unit_cannot_be_changed_by_common_mass_assignment(): void
    {
        $visitor = Visitor::factory()->create();
        $originalUnit = $visitor->unit_id;

        $visitor->fill(['unit_id' => Unit::factory()->create()->id])->save();

        $this->assertSame($originalUnit, $visitor->refresh()->unit_id);
        $this->assertSame($originalUnit, $visitor->unit->id);
        $this->assertTrue($visitor->unit->visitors->contains($visitor));
    }

    public function test_authorization_factories_keep_visitor_and_resident_in_the_same_unit(): void
    {
        $visitor = Visitor::factory()->create();
        $unit = Unit::factory()->create();
        $authorizations = [
            VisitorAuthorization::factory()->create(),
            VisitorAuthorization::factory()->create(['visitor_id' => $visitor->id]),
            VisitorAuthorization::factory()->create(['visitor_id' => $visitor]),
            VisitorAuthorization::factory()->create(['visitor_id' => Visitor::factory()]),
            VisitorAuthorization::factory()->for(Visitor::factory())->create(),
            VisitorAuthorization::factory()->for(Unit::factory())->create(),
            VisitorAuthorization::factory()->create(['unit_id' => Unit::factory()]),
            VisitorAuthorization::factory()->create(['unit_id' => $unit->id]),
        ];

        foreach ($authorizations as $authorization) {
            $this->assertSame($authorization->unit_id, $authorization->visitor->unit_id);
            $this->assertSame($authorization->unit_id, $authorization->resident->unit_id);
        }

        $pending = VisitorAuthorization::factory()->pendingData()->create();
        $this->assertNull($pending->visitor_id);
        $this->assertSame($pending->unit_id, $pending->resident->unit_id);
    }

    /** @return array<string, array{bool}> */
    public static function creationFlows(): array
    {
        return ['direct' => [false], 'external invitation' => [true]];
    }

    /** @param array<string, mixed> $overrides */
    private function createDirect(User $resident, string $name, string $phone, array $overrides = []): VisitorAuthorization
    {
        $this->actingAs($resident)->post(route('morador.visitors.store'), [
            'name' => $name,
            'cpf' => '52998224725',
            'phone' => $phone,
            'start_date' => now()->addHour()->toDateTimeString(),
            'end_date' => now()->addHours(3)->toDateTimeString(),
            ...$overrides,
        ])->assertSessionHasNoErrors()->assertRedirect();

        return VisitorAuthorization::where('resident_id', $resident->id)->latest('id')->firstOrFail();
    }

    private function completeInvitation(User $resident, string $name, string $phone, int $manipulatedUnitId): VisitorAuthorization
    {
        $response = $this->actingAs($resident)->post(route('morador.visitor-invitations.store'), [
            'start_date' => now()->addHour()->toDateTimeString(),
            'end_date' => now()->addHours(3)->toDateTimeString(),
            'unit_id' => $manipulatedUnitId,
        ])->assertSessionHasNoErrors()->assertRedirect()->assertSessionHas('invitation_url');

        $token = Str::afterLast($response->getSession()->get('invitation_url'), '/');
        $this->post(route('logout'))->assertRedirect();
        $this->get(route('visitor-invitations.show', $token))->assertOk();
        $this->post(route('visitor-invitations.complete', $token), [
            'name' => $name,
            'cpf' => '529.982.247-25',
            'phone' => $phone,
            'confirmed' => '1',
            'unit_id' => $manipulatedUnitId,
        ])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('visitor-invitations/completed')->has('qr_svg'));

        return VisitorAuthorization::where('resident_id', $resident->id)->latest('id')->firstOrFail();
    }

    private function assertResidentViews(User $resident, VisitorAuthorization $authorization, VisitorAuthorization $other, string $name, string $phone): void
    {
        $list = $this->actingAs($resident)->get(route('morador.visitors.index', ['search' => '52998224725']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('authorizations.data', 1)
            ->where('authorizations.data.0.id', $authorization->id)
            ->where('authorizations.data.0.visitor.name', $name)
            ->where('authorizations.data.0.visitor.cpf', '***.***.***-25')
            ->missing('authorizations.data.0.visitor.phone'));

        $detail = $this->get(route('morador.visitors.show', $authorization))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('authorization.visitor', ['name' => $name, 'cpf' => '529.982.247-25', 'phone' => $phone]));

        foreach ([$list, $detail] as $response) {
            $this->assertStringNotContainsString($other->visitor->name, $response->getContent());
            $this->assertStringNotContainsString($other->visitor->phone, $response->getContent());
        }

        $this->get(route('morador.visitors.show', $other))->assertForbidden();
    }
}
