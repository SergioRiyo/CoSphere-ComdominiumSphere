<?php

namespace Tests\Feature;

use App\Enums\VisitorAuthorizationStatus;
use App\Models\Unit;
use App\Models\User;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorAuthorizationCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_can_cancel_their_active_authorization_and_invalidate_its_qr_code(): void
    {
        [$resident, $authorization] = $this->createAuthorization();

        $this->actingAs($resident)
            ->delete(route('morador.visitors.destroy', $authorization))
            ->assertRedirect();

        $this->assertSame(
            VisitorAuthorizationStatus::Canceled,
            $authorization->refresh()->status,
        );

        $this->actingAs($resident)
            ->get(route('morador.visitors.qr-code', $authorization))
            ->assertNotFound();

        $this->actingAs($resident)
            ->get(route('morador.visitors.access-code', $authorization))
            ->assertNotFound();

        $doorman = User::factory()->porteiro()->create();
        $this->actingAs($doorman)
            ->postJson(route('portaria.visitor-authorizations.validate'), [
                'access_code' => $authorization->access_code,
            ])
            ->assertJsonPath('allowed', false)
            ->assertJsonPath('reason', 'canceled');
    }

    public function test_resident_can_cancel_a_pending_invitation_and_revoke_its_public_link(): void
    {
        [$resident, $authorization] = $this->createAuthorization();
        $token = str_repeat('A', 64);
        $authorization->update([
            'visitor_id' => null,
            'access_code' => null,
            'status' => VisitorAuthorizationStatus::PendingData,
            'invitation_token_hash' => hash('sha256', $token),
            'invitation_expires_at' => now()->addDay(),
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(3),
        ]);

        $this->actingAs($resident)
            ->delete(route('morador.visitors.destroy', $authorization))
            ->assertRedirect();

        $authorization->refresh();
        $this->assertSame(VisitorAuthorizationStatus::Canceled, $authorization->status);
        $this->assertNull($authorization->invitation_token_hash);

        $this->get(route('visitor-invitations.show', $token))->assertNotFound();
    }

    public function test_cancellation_rejects_invalid_state_transitions_and_open_entries(): void
    {
        [$resident] = $this->createAuthorization();
        $authorizations = [
            VisitorAuthorization::factory()->expired()->create([
                'unit_id' => $resident->unit_id,
                'resident_id' => $resident->id,
            ]),
            VisitorAuthorization::factory()->used()->create([
                'unit_id' => $resident->unit_id,
                'resident_id' => $resident->id,
            ]),
            VisitorAuthorization::factory()->canceled()->create([
                'unit_id' => $resident->unit_id,
                'resident_id' => $resident->id,
            ]),
        ];
        $openAuthorization = VisitorAuthorization::factory()->active()->create([
            'unit_id' => $resident->unit_id,
            'resident_id' => $resident->id,
        ]);
        VisitorAccess::factory()->open()->create([
            'visitor_authorization_id' => $openAuthorization->id,
        ]);
        $authorizations[] = $openAuthorization;

        foreach ($authorizations as $authorization) {
            $initialStatus = $authorization->status;

            $this->actingAs($resident)
                ->delete(route('morador.visitors.destroy', $authorization))
                ->assertRedirect();

            $this->assertSame($initialStatus, $authorization->refresh()->status);
        }
    }

    public function test_only_the_resident_who_created_the_authorization_can_cancel_it(): void
    {
        [$resident, $authorization] = $this->createAuthorization();
        $residentFromSameUnit = User::factory()->morador()->create([
            'unit_id' => $resident->unit_id,
        ]);
        $residentFromAnotherUnit = User::factory()->morador()->create();

        $this->actingAs($residentFromSameUnit)
            ->delete(route('morador.visitors.destroy', $authorization))
            ->assertForbidden();

        $this->actingAs($residentFromAnotherUnit)
            ->delete(route('morador.visitors.destroy', $authorization))
            ->assertForbidden();

        $this->assertSame(
            VisitorAuthorizationStatus::Active,
            $authorization->refresh()->status,
        );
    }

    /**
     * @return array{0: User, 1: VisitorAuthorization}
     */
    private function createAuthorization(): array
    {
        $unit = Unit::factory()->create(['status' => 'active']);
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $authorization = VisitorAuthorization::factory()->active()->create([
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
        ]);

        return [$resident, $authorization];
    }
}
