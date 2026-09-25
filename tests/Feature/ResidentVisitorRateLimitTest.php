<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VisitorAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResidentVisitorRateLimitTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('operations')]
    public function test_resident_endpoints_enforce_their_limit_and_user_ip_key(string $operation, int $limit, int $successStatus): void
    {
        $this->freezeTime();
        $resident = User::factory()->morador()->create();
        $this->actingAs($resident)->withServerVariables(['REMOTE_ADDR' => '192.0.2.20']);

        for ($attempt = 0; $attempt < $limit; $attempt++) {
            $this->attempt($operation, $resident)
                ->assertStatus($successStatus)
                ->assertSessionHasNoErrors()
                ->assertHeader('X-RateLimit-Limit', (string) $limit)
                ->assertHeader('X-RateLimit-Remaining', (string) ($limit - $attempt - 1));
        }

        $this->attempt($operation, $resident)->assertTooManyRequests();

        foreach (self::operations() as [$otherOperation, $otherLimit]) {
            if ($otherOperation !== $operation && $otherLimit === $limit) {
                $this->attempt($otherOperation, $resident)->assertTooManyRequests();
            }
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.21']);
        $this->attempt($operation, $resident)->assertStatus($successStatus)->assertSessionHasNoErrors();

        $otherResident = User::factory()->morador()->create();
        $this->actingAs($otherResident)->withServerVariables(['REMOTE_ADDR' => '192.0.2.20']);
        $this->attempt($operation, $otherResident)->assertStatus($successStatus)->assertSessionHasNoErrors();
    }

    /** @return array<string, array{string, int, int}> */
    public static function operations(): array
    {
        return [
            'direct authorization' => ['authorization', 10, 302],
            'create invitation' => ['invitation', 10, 302],
            'cancel authorization' => ['cancellation', 10, 302],
            'qr code' => ['qr-code', 20, 200],
            'manual access code' => ['access-code', 20, 200],
        ];
    }

    private function attempt(string $operation, User $resident): TestResponse
    {
        $this->from(route('morador.visitors.index'));

        if ($operation === 'authorization' || $operation === 'invitation') {
            $period = [
                'start_date' => now()->addDay()->toDateTimeString(),
                'end_date' => now()->addDays(2)->toDateTimeString(),
            ];

            return $operation === 'invitation'
                ? $this->post(route('morador.visitor-invitations.store'), $period)
                : $this->post(route('morador.visitors.store'), [
                    ...$period,
                    'name' => 'Visitante do limite',
                    'cpf' => '52998224725',
                    'phone' => '65999999999',
                ]);
        }

        $authorization = VisitorAuthorization::factory()->active()->create([
            'resident_id' => $resident->id,
            'unit_id' => $resident->unit_id,
        ]);

        if ($operation === 'cancellation') {
            return $this->delete(route('morador.visitors.destroy', $authorization));
        }

        return $this->get(route('morador.visitors.'.$operation, $authorization));
    }
}
