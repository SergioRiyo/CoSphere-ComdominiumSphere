<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use App\Models\VisitorAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->post(route('visitor-invitations.complete', $token), $payload)->assertOk();
        $authorization->refresh();
        $this->assertNotNull($authorization->visitor_id);
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
        $authorization->update(['status' => 'canceled']);
        $this->get(route('visitor-invitations.show', $token))->assertNotFound();
        $this->get(route('visitor-invitations.show', str_repeat('C', 64)))->assertNotFound();
    }

    public function test_public_invitation_route_is_rate_limited(): void
    {
        $token = str_repeat('D', 64);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->get(route('visitor-invitations.show', $token))->assertNotFound();
        }

        $this->get(route('visitor-invitations.show', $token))->assertTooManyRequests();
    }
}
