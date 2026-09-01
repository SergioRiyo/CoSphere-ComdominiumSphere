<?php

namespace Tests\Feature;

use App\Enums\VisitorAuthorizationStatus;
use App\Models\Unit;
use App\Models\User;
use App\Models\VisitorAuthorization;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VisitorInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_creates_hashed_invitation_and_visitor_completes_it_once(): void
    {
        $unit = Unit::factory()->create(['status' => 'active']);
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $this->actingAs($resident)->post(route('morador.visitor-invitations.store'), ['start_date' => now()->addDays(2), 'end_date' => now()->addDays(3)])->assertRedirect();
        $authorization = VisitorAuthorization::firstOrFail();
        $this->assertNotNull($authorization->invitation_token_hash);
        $token = str_repeat('A', 64);
        $authorization->forceFill(['invitation_token_hash' => hash('sha256', $token)])->save();
        $this->get(route('visitor-invitations.show', $token))->assertOk();
        $payload = ['name' => 'Visitante Teste', 'cpf' => '52998224725', 'phone' => '65999999999', 'confirmed' => '1'];
        $this->post(route('visitor-invitations.complete', $token), $payload)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('visitor-invitations/completed')
                ->has('qr_svg'));
        $authorization->refresh();
        $this->assertNotNull($authorization->visitor_id);
        $this->assertSame('529.982.247-25', $authorization->visitor->cpf);
        $this->assertSame('(65) 99999-9999', $authorization->visitor->phone);
        $this->assertNotNull($authorization->access_code);
        $this->assertNotNull($authorization->invitation_used_at);
        $this->assertNull($authorization->invitation_token_hash);
        $this->post(route('visitor-invitations.complete', $token), $payload)->assertNotFound();
    }

    public function test_expired_revoked_and_invalid_invitations_are_unavailable(): void
    {
        $token = str_repeat('B', 64);
        $authorization = VisitorAuthorization::factory()->pendingData($token)->create(['invitation_expires_at' => now()->subMinute()]);
        $this->get(route('visitor-invitations.show', $token))->assertNotFound();
        $revokedToken = str_repeat('R', 64);
        $authorization = VisitorAuthorization::factory()->pendingData($revokedToken)->create();
        $authorization->forceFill(['status' => 'canceled', 'invitation_token_hash' => null])->save();
        $this->get(route('visitor-invitations.show', $revokedToken))->assertNotFound();
        $this->get(route('visitor-invitations.show', str_repeat('C', 64)))->assertNotFound();
    }

    public function test_completion_requires_valid_visitor_data_and_keeps_invitation_pending(): void
    {
        $token = str_repeat('E', 64);
        $authorization = VisitorAuthorization::factory()->pendingData($token)->create();

        $this->from(route('visitor-invitations.show', $token))
            ->post(route('visitor-invitations.complete', $token), [
                'name' => ' ',
                'cpf' => '123',
                'phone' => '123',
                'vehicle_plate' => 'invalid',
                'confirmed' => '0',
            ])
            ->assertRedirect(route('visitor-invitations.show', $token))
            ->assertSessionHasErrors(['name', 'cpf', 'phone', 'vehicle_plate', 'confirmed']);

        $authorization->refresh();
        $this->assertSame('pending_data', $authorization->status->value);
        $this->assertNull($authorization->visitor_id);
        $this->assertNull($authorization->invitation_used_at);
    }

    public function test_completion_normalizes_the_vehicle_plate(): void
    {
        $token = str_repeat('F', 64);
        $authorization = VisitorAuthorization::factory()->pendingData($token)->create();

        $this->post(route('visitor-invitations.complete', $token), [
            'name' => 'Visitante Teste',
            'cpf' => '52998224725',
            'phone' => '65999999999',
            'vehicle_plate' => 'abc1d23',
            'confirmed' => '1',
        ])->assertOk();

        $this->assertSame('ABC-1D23', $authorization->refresh()->vehicle_plate);
    }

    public function test_public_invitation_route_is_rate_limited(): void
    {
        $token = str_repeat('D', 64);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->get(route('visitor-invitations.show', $token))->assertNotFound();
        }

        $this->get(route('visitor-invitations.show', $token))->assertTooManyRequests();
    }

    public function test_public_invitation_completion_is_rate_limited(): void
    {
        $token = str_repeat('G', 64);
        $payload = [
            'name' => 'Visitante Teste',
            'cpf' => '52998224725',
            'phone' => '65999999999',
            'confirmed' => '1',
        ];

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post(route('visitor-invitations.complete', $token), $payload)
                ->assertNotFound();
        }

        $this->post(route('visitor-invitations.complete', $token), $payload)
            ->assertTooManyRequests();
    }

    public function test_public_invitation_responses_are_not_cacheable(): void
    {
        $token = str_repeat('H', 64);
        VisitorAuthorization::factory()->pendingData($token)->create();

        $this->get(route('visitor-invitations.show', $token))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $this->post(route('visitor-invitations.complete', $token), [
            'name' => 'Visitante Teste',
            'cpf' => '52998224725',
            'phone' => '65999999999',
            'confirmed' => '1',
        ])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_invitation_expiration_is_the_earlier_of_24_hours_or_visit_start(): void
    {
        $now = Carbon::parse('2026-09-01 10:00:00');
        $this->travelTo($now);
        $unit = Unit::factory()->create(['status' => 'active']);
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);

        $this->actingAs($resident)
            ->post(route('morador.visitor-invitations.store'), [
                'start_date' => $now->copy()->addHours(12),
                'end_date' => $now->copy()->addHours(14),
            ])
            ->assertRedirect();

        $this->assertTrue(
            $now->copy()->addHours(12)->equalTo(
                VisitorAuthorization::query()->sole()->invitation_expires_at,
            ),
        );

        $this->actingAs($resident)
            ->post(route('morador.visitor-invitations.store'), [
                'start_date' => $now->copy()->addDays(2),
                'end_date' => $now->copy()->addDays(3),
            ])
            ->assertRedirect();

        $this->assertTrue(
            $now->copy()->addDay()->equalTo(
                VisitorAuthorization::query()->latest('id')->firstOrFail()->invitation_expires_at,
            ),
        );
    }

    public function test_invitation_is_unavailable_when_the_visit_start_is_reached(): void
    {
        $token = str_repeat('I', 64);
        VisitorAuthorization::factory()->pendingData($token)->create([
            'start_date' => now(),
            'invitation_expires_at' => now()->addDay(),
        ]);

        $this->get(route('visitor-invitations.show', $token))
            ->assertNotFound();

        $this->post(route('visitor-invitations.complete', $token), $this->validVisitorData())
            ->assertNotFound();
    }

    public function test_invitation_completion_ignores_ownership_period_and_status_fields(): void
    {
        $token = str_repeat('J', 64);
        $authorization = VisitorAuthorization::factory()->pendingData($token)->create();
        $otherUnit = Unit::factory()->create();
        $otherResident = User::factory()->morador()->create(['unit_id' => $otherUnit->id]);

        $this->post(route('visitor-invitations.complete', $token), [
            ...$this->validVisitorData(),
            'resident_id' => $otherResident->id,
            'unit_id' => $otherUnit->id,
            'start_date' => now()->addMonth(),
            'end_date' => now()->addMonths(2),
            'status' => 'canceled',
        ])->assertOk();

        $authorization->refresh();

        $this->assertSame(VisitorAuthorizationStatus::Active, $authorization->status);
        $this->assertNotSame($otherResident->id, $authorization->resident_id);
        $this->assertNotSame($otherUnit->id, $authorization->unit_id);
        $this->assertTrue($authorization->start_date->isTomorrow());
    }

    /** @return array{name: string, cpf: string, phone: string, confirmed: string} */
    private function validVisitorData(): array
    {
        return [
            'name' => 'Visitante Teste',
            'cpf' => '52998224725',
            'phone' => '65999999999',
            'confirmed' => '1',
        ];
    }
}
