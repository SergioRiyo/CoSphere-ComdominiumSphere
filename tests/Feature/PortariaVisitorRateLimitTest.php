<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PortariaVisitorRateLimitTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('operations')]
    public function test_sensitive_operations_are_rate_limited_per_authenticated_doorman_and_ip(string $operation, int $successStatus): void
    {
        $this->freezeTime();
        $doorman = User::factory()->porteiro()->create();
        $resident = User::factory()->morador()->create();
        $this->actingAs($doorman)->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $response = $this->attempt($operation, $resident, $doorman)
                ->assertStatus($successStatus)
                ->assertHeader('X-RateLimit-Limit', '30')
                ->assertHeader('X-RateLimit-Remaining', (string) (29 - $attempt));

            match ($operation) {
                'validation' => $response->assertJsonPath('allowed', true),
                'entry' => $response->assertJsonPath('registered', true),
                'exit' => $response->assertInertiaFlash('toast.type', 'success'),
            };
        }

        $this->attempt($operation, $resident, $doorman)->assertTooManyRequests();

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11']);
        $this->attempt($operation, $resident, $doorman)->assertStatus($successStatus);

        $otherDoorman = User::factory()->porteiro()->create();
        $this->actingAs($otherDoorman)->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);
        $this->attempt($operation, $resident, $otherDoorman)->assertStatus($successStatus);

        $this->actingAs($doorman);

        foreach (array_keys(self::operations()) as $otherOperation) {
            if ($otherOperation !== $operation) {
                $this->attempt($otherOperation, $resident, $doorman)
                    ->assertStatus(self::operations()[$otherOperation][1]);
            }
        }
    }

    /** @return array<string, array{string, int}> */
    public static function operations(): array
    {
        return [
            'validation' => ['validation', 200],
            'entry' => ['entry', 201],
            'exit' => ['exit', 302],
        ];
    }

    private function attempt(string $operation, User $resident, User $doorman): TestResponse
    {
        $authorization = VisitorAuthorization::factory()->active()->create([
            'resident_id' => $resident->id,
            'unit_id' => $resident->unit_id,
        ]);

        if ($operation === 'exit') {
            $access = VisitorAccess::factory()->open()->create([
                'visitor_authorization_id' => $authorization->id,
                'doorman_id' => $doorman->id,
            ]);

            return $this->from(route('portaria.visitor-accesses.index'))
                ->post(route('portaria.visitor-accesses.exit', $access));
        }

        return $this->postJson(route($operation === 'validation'
            ? 'portaria.visitor-authorizations.validate'
            : 'portaria.visitor-accesses.store'), ['access_code' => $authorization->access_code]);
    }
}
