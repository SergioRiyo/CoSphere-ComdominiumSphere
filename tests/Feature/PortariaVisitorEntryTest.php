<?php

namespace Tests\Feature;

use App\Enums\VisitorAccessStatus;
use App\Enums\VisitorAuthorizationStatus;
use App\Models\User;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortariaVisitorEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_verified_doorman_registers_a_valid_entry(): void
    {
        $doorman = User::factory()->porteiro()->create();
        $authorization = VisitorAuthorization::factory()->active()->create();

        $response = $this->actingAs($doorman)
            ->postJson(route('portaria.visitor-accesses.store'), [
                'access_code' => $authorization->access_code,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('registered', true)
            ->assertJsonPath('message', 'Entrada registrada com sucesso.')
            ->assertJsonPath('entry.entry_time', fn (?string $entryTime): bool => $entryTime !== null)
            ->assertJsonMissingPath('entry.doorman_id')
            ->assertJsonMissingPath('entry.visitor_authorization_id')
            ->assertJsonMissingPath('entry.access_code');

        $access = VisitorAccess::query()->sole();

        $this->assertSame($authorization->id, $access->visitor_authorization_id);
        $this->assertSame($doorman->id, $access->doorman_id);
        $this->assertNotNull($access->entry_time);
        $this->assertNull($access->exit_time);
        $this->assertNull($access->exit_doorman_id);
        $this->assertSame(VisitorAccessStatus::Validated, $access->validation_status);
        $this->assertSame(VisitorAuthorizationStatus::Active, $authorization->refresh()->status);
        $this->assertStringNotContainsString($authorization->access_code, $response->getContent());
    }

    public function test_guest_cannot_register_an_entry(): void
    {
        $authorization = VisitorAuthorization::factory()->active()->create();

        $this->postJson(route('portaria.visitor-accesses.store'), [
            'access_code' => $authorization->access_code,
        ])->assertUnauthorized();

        $this->assertDatabaseCount('visitor_accesses', 0);
    }

    public function test_administrator_and_resident_cannot_register_an_entry(): void
    {
        $authorization = VisitorAuthorization::factory()->active()->create();

        foreach ([
            User::factory()->admin()->create(),
            User::factory()->morador()->create(),
        ] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)
                ->postJson(route('portaria.visitor-accesses.store'), [
                    'access_code' => $authorization->access_code,
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('visitor_accesses', 0);
    }

    public function test_inactive_doorman_cannot_register_an_entry(): void
    {
        $authorization = VisitorAuthorization::factory()->active()->create();
        $inactiveDoorman = User::factory()->porteiro()->inactive()->create();

        $this->actingAs($inactiveDoorman)
            ->postJson(route('portaria.visitor-accesses.store'), [
                'access_code' => $authorization->access_code,
            ])
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseCount('visitor_accesses', 0);
    }

    public function test_unverified_doorman_cannot_register_an_entry(): void
    {
        $authorization = VisitorAuthorization::factory()->active()->create();
        $unverifiedDoorman = User::factory()->porteiro()->unverified()->create();

        $this->actingAs($unverifiedDoorman)
            ->postJson(route('portaria.visitor-accesses.store'), [
                'access_code' => $authorization->access_code,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('visitor_accesses', 0);
    }

    public function test_non_liberated_authorizations_do_not_register_an_open_entry(): void
    {
        $doorman = User::factory()->porteiro()->create();
        $authorizations = [
            'Autorização ainda não está ativa.' => VisitorAuthorization::factory()->future()->create(),
            'Autorização expirada.' => VisitorAuthorization::factory()->expired()->create(),
            'Autorização cancelada.' => VisitorAuthorization::factory()->canceled()->create(),
            'Autorização já utilizada.' => VisitorAuthorization::factory()->used()->create(),
            'Autorização aguardando preenchimento de dados.' => VisitorAuthorization::factory()
                ->pendingData()
                ->create(['access_code' => 'csa_pendente_para_entrada']),
        ];

        foreach ($authorizations as $expectedMessage => $authorization) {
            $this->actingAs($doorman)
                ->postJson(route('portaria.visitor-accesses.store'), [
                    'access_code' => $authorization->access_code,
                ])
                ->assertOk()
                ->assertExactJson([
                    'registered' => false,
                    'message' => $expectedMessage,
                    'entry' => null,
                ]);

            $this->assertSame(0, $this->openAccessCount($authorization));
        }
    }

    public function test_repeated_entry_request_does_not_create_a_second_open_access(): void
    {
        $doorman = User::factory()->porteiro()->create();
        $authorization = VisitorAuthorization::factory()->active()->create();
        $payload = ['access_code' => $authorization->access_code];

        $this->actingAs($doorman)
            ->postJson(route('portaria.visitor-accesses.store'), $payload)
            ->assertCreated();

        $this->postJson(route('portaria.visitor-accesses.store'), $payload)
            ->assertOk()
            ->assertExactJson([
                'registered' => false,
                'message' => 'Este visitante já possui uma entrada registrada sem saída.',
                'entry' => null,
            ]);

        $this->assertSame(1, $this->openAccessCount($authorization));
        $this->assertSame(1, $authorization->visitorAccesses()->count());
        $this->assertSame(VisitorAuthorizationStatus::Active, $authorization->refresh()->status);
    }

    public function test_client_ids_cannot_replace_the_authenticated_doorman_or_authorization_relations(): void
    {
        $authenticatedDoorman = User::factory()->porteiro()->create();
        $otherDoorman = User::factory()->porteiro()->create();
        $authorization = VisitorAuthorization::factory()->active()->create();

        $this->actingAs($authenticatedDoorman)
            ->postJson(route('portaria.visitor-accesses.store'), [
                'access_code' => $authorization->access_code,
                'doorman_id' => $otherDoorman->id,
                'user_id' => $otherDoorman->id,
                'resident_id' => $otherDoorman->id,
                'unit_id' => PHP_INT_MAX,
            ])
            ->assertCreated();

        $access = VisitorAccess::query()->sole();

        $this->assertSame($authenticatedDoorman->id, $access->doorman_id);
        $this->assertSame($authorization->id, $access->visitor_authorization_id);
        $this->assertSame($authorization->resident_id, $access->visitorAuthorization->resident_id);
        $this->assertSame($authorization->unit_id, $access->visitorAuthorization->unit_id);
    }

    public function test_entry_revalidates_authorization_after_manual_validation(): void
    {
        $doorman = User::factory()->porteiro()->create();
        $authorization = VisitorAuthorization::factory()->active()->create();

        $this->actingAs($doorman)
            ->postJson(route('portaria.visitor-authorizations.validate'), [
                'access_code' => $authorization->access_code,
            ])
            ->assertJsonPath('allowed', true);

        $authorization->update(['status' => VisitorAuthorizationStatus::Canceled]);

        $this->postJson(route('portaria.visitor-accesses.store'), [
            'access_code' => $authorization->access_code,
        ])->assertExactJson([
            'registered' => false,
            'message' => 'Autorização cancelada.',
            'entry' => null,
        ]);

        $this->assertSame(0, $this->openAccessCount($authorization));
    }

    public function test_unknown_access_code_returns_a_controlled_denial(): void
    {
        $doorman = User::factory()->porteiro()->create();

        $this->actingAs($doorman)
            ->postJson(route('portaria.visitor-accesses.store'), [
                'access_code' => 'csa_codigo_inexistente',
            ])
            ->assertOk()
            ->assertExactJson([
                'registered' => false,
                'message' => 'Código de acesso inválido.',
                'entry' => null,
            ]);

        $this->assertDatabaseCount('visitor_accesses', 0);
    }

    public function test_known_denied_entry_is_recorded_with_the_authenticated_doorman_and_reason(): void
    {
        $doorman = User::factory()->porteiro()->create();
        $authorization = VisitorAuthorization::factory()->canceled()->create();

        $this->actingAs($doorman)
            ->postJson(route('portaria.visitor-accesses.store'), [
                'access_code' => $authorization->access_code,
            ])
            ->assertOk()
            ->assertJsonPath('registered', false)
            ->assertJsonPath('message', 'Autorização cancelada.');

        $this->assertDatabaseHas('visitor_accesses', [
            'visitor_authorization_id' => $authorization->id,
            'doorman_id' => $doorman->id,
            'validation_status' => VisitorAccessStatus::Rejected->value,
            'observations' => 'Autorização cancelada.',
        ]);
    }

    public function test_access_code_is_required_to_register_an_entry(): void
    {
        $doorman = User::factory()->porteiro()->create();

        $this->actingAs($doorman)
            ->postJson(route('portaria.visitor-accesses.store'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('access_code');

        $this->assertDatabaseCount('visitor_accesses', 0);
    }

    private function openAccessCount(VisitorAuthorization $authorization): int
    {
        return $authorization->visitorAccesses()
            ->where('validation_status', VisitorAccessStatus::Validated->value)
            ->whereNull('exit_time')
            ->count();
    }
}
