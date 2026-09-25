<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VisitorAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VisitorInvitationRateLimitTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('operations')]
    public function test_public_invitation_endpoints_share_a_limit_by_ip_not_token_or_user(string $operation): void
    {
        $this->freezeTime();
        $resident = User::factory()->morador()->create();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.30']);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->attempt($operation, $resident)
                ->assertOk()
                ->assertSessionHasNoErrors()
                ->assertHeader('X-RateLimit-Limit', '10')
                ->assertHeader('X-RateLimit-Remaining', (string) (9 - $attempt));
        }

        $this->attempt($operation, $resident)->assertTooManyRequests();
        $otherOperation = $operation === 'show' ? 'complete' : 'show';
        $this->attempt($otherOperation, $resident)->assertTooManyRequests();

        $this->actingAs($resident);
        $this->attempt($operation, $resident)->assertTooManyRequests();

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.31']);
        $this->attempt($operation, $resident)->assertOk()->assertSessionHasNoErrors();
    }

    /** @return array<string, array{string}> */
    public static function operations(): array
    {
        return [
            'show public invitation' => ['show'],
            'complete public invitation' => ['complete'],
        ];
    }

    private function attempt(string $operation, User $resident): TestResponse
    {
        $token = Str::random(64);
        VisitorAuthorization::factory()->pendingData($token)->create([
            'resident_id' => $resident->id,
            'unit_id' => $resident->unit_id,
        ]);

        if ($operation === 'show') {
            return $this->get(route('visitor-invitations.show', $token));
        }

        return $this->post(route('visitor-invitations.complete', $token), [
            'name' => 'Visitante do convite',
            'cpf' => '52998224725',
            'phone' => '65999999999',
            'confirmed' => '1',
        ]);
    }
}
