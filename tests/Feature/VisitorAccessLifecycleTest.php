<?php

namespace Tests\Feature;

use App\Enums\VisitorAuthorizationStatus;
use App\Models\Unit;
use App\Models\User;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VisitorAccessLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_authorization_completes_the_full_access_lifecycle(): void
    {
        $unit = Unit::factory()->create(['status' => 'active']);
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $doorman = User::factory()->porteiro()->create();

        $this->actingAs($resident)
            ->post(route('morador.visitors.store'), [
                'name' => 'Visitante Direto',
                'cpf' => '52998224725',
                'phone' => '65999999999',
                'vehicle_plate' => 'abc1d23',
                'start_date' => now()->addMinute(),
                'end_date' => now()->addHours(2),
            ])
            ->assertRedirect();

        $authorization = VisitorAuthorization::query()->sole();

        $this->travel(2)->minutes();

        $accessCode = $this->actingAs($resident)
            ->get(route('morador.visitors.access-code', $authorization))
            ->assertOk()
            ->getContent();

        $this->actingAs($doorman)
            ->postJson(route('portaria.visitor-authorizations.validate'), [
                'access_code' => $accessCode,
            ])
            ->assertOk()
            ->assertJsonPath('allowed', true);

        $this->postJson(route('portaria.visitor-accesses.store'), [
            'access_code' => $accessCode,
        ])->assertCreated();

        $access = VisitorAccess::query()->sole();

        $this->post(route('portaria.visitor-accesses.exit', $access))
            ->assertRedirect();

        $this->assertSame(VisitorAuthorizationStatus::Used, $authorization->refresh()->status);
        $this->assertNotNull($access->refresh()->exit_time);

        $this->actingAs($resident)
            ->get(route('morador.visitors.show', $authorization))
            ->assertInertia(fn (Assert $page) => $page
                ->where('authorization.status', VisitorAuthorizationStatus::Used->value)
                ->where('authorization.visitor.name', 'Visitante Direto'));
    }

    public function test_external_invitation_completes_the_full_access_lifecycle(): void
    {
        $unit = Unit::factory()->create(['status' => 'active']);
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $doorman = User::factory()->porteiro()->create();

        $invitationResponse = $this->actingAs($resident)
            ->from(route('morador.visitors.index'))
            ->post(route('morador.visitor-invitations.store'), [
                'start_date' => now()->addHour(),
                'end_date' => now()->addHours(3),
            ])
            ->assertRedirect(route('morador.visitors.index'))
            ->assertSessionHas('invitation_url');

        $token = Str::afterLast(
            (string) $invitationResponse->getSession()->get('invitation_url'),
            '/',
        );

        $this->get(route('visitor-invitations.show', $token))
            ->assertOk();

        $this->post(route('visitor-invitations.complete', $token), [
            'name' => 'Visitante Convidado',
            'cpf' => '39053344705',
            'phone' => '11987654321',
            'confirmed' => '1',
        ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('visitor-invitations/completed')
                ->has('qr_svg'));

        $authorization = VisitorAuthorization::query()->sole();
        $this->assertSame(VisitorAuthorizationStatus::Active, $authorization->status);

        $this->travel(2)->hours();

        $this->actingAs($doorman)
            ->postJson(route('portaria.visitor-authorizations.validate'), [
                'access_code' => $authorization->access_code,
            ])
            ->assertOk()
            ->assertJsonPath('allowed', true);

        $this->postJson(route('portaria.visitor-accesses.store'), [
            'access_code' => $authorization->access_code,
        ])->assertCreated();

        $access = VisitorAccess::query()->sole();

        $this->post(route('portaria.visitor-accesses.exit', $access))
            ->assertRedirect();

        $this->assertSame(VisitorAuthorizationStatus::Used, $authorization->refresh()->status);
        $this->assertNotNull($access->refresh()->exit_time);
    }
}
