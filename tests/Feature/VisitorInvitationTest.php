<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use App\Models\VisitorAuthorization;
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
        $authorization->update(['invitation_token_hash' => hash('sha256', $token)]);
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
        $authorization->update(['status' => 'canceled', 'invitation_token_hash' => null]);
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
}
