<?php

namespace Tests\Feature;

use App\Enums\VisitorAuthorizationStatus;
use App\Models\Unit;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use App\Services\VisitorQrCodeService;
use App\Services\VisitorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PortariaVisitorValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_verified_doorman_can_access_the_manual_validation_page(): void
    {
        $doorman = User::factory()->porteiro()->create();

        $this->actingAs($doorman)
            ->get(route('portaria.visitor-authorizations.validation'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portaria/visitor-validation')
                ->where('timezone', config('app.timezone'))
                ->missing('authorization')
                ->missing('visitor')
                ->missing('access_code'));
    }

    public function test_guest_cannot_access_the_manual_validation_page(): void
    {
        $this->get(route('portaria.visitor-authorizations.validation'))
            ->assertRedirect(route('login'));
    }

    public function test_administrator_and_resident_cannot_access_the_manual_validation_page(): void
    {
        foreach ([
            User::factory()->admin()->create(),
            User::factory()->morador()->create(),
        ] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)
                ->get(route('portaria.visitor-authorizations.validation'))
                ->assertForbidden();
        }
    }

    public function test_inactive_doorman_cannot_access_the_manual_validation_page(): void
    {
        $inactiveDoorman = User::factory()->porteiro()->inactive()->create();

        $this->actingAs($inactiveDoorman)
            ->get(route('portaria.visitor-authorizations.validation'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_active_verified_doorman_can_validate_an_active_authorization(): void
    {
        $unit = Unit::factory()->create([
            'block' => 'A',
            'number' => '101',
            'status' => 'active',
        ]);
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $visitor = Visitor::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'João Visitante',
            'cpf' => '529.982.247-25',
            'phone' => '(65) 99999-9999',
        ]);
        $authorization = VisitorAuthorization::factory()->active()->create([
            'visitor_id' => $visitor->id,
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
            'vehicle_plate' => 'ABC1D23',
            'invitation_token_hash' => hash('sha256', 'convite-secreto'),
        ]);
        $doorman = User::factory()->porteiro()->create();

        $response = $this->actingAs($doorman)
            ->postJson(route('portaria.visitor-authorizations.validate'), [
                'access_code' => $authorization->access_code,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('allowed', true)
            ->assertJsonPath('reason', null)
            ->assertJsonPath('message', 'Acesso liberado.')
            ->assertJsonPath('authorization.visitor_name', $visitor->name)
            ->assertJsonPath('authorization.unit.block', $unit->block)
            ->assertJsonPath('authorization.unit.number', $unit->number)
            ->assertJsonPath('authorization.vehicle_plate', 'ABC1D23')
            ->assertJsonPath('authorization.status', VisitorAuthorizationStatus::Active->value)
            ->assertJsonMissingPath('authorization.cpf')
            ->assertJsonMissingPath('authorization.phone')
            ->assertJsonMissingPath('authorization.access_code')
            ->assertJsonMissingPath('authorization.invitation_token_hash')
            ->assertJsonMissingPath('authorization.resident_id')
            ->assertJsonMissingPath('authorization.unit_id');

        $serializedResult = $response->getContent();

        $this->assertStringNotContainsString($visitor->cpf, $serializedResult);
        $this->assertStringNotContainsString($visitor->phone, $serializedResult);
        $this->assertStringNotContainsString($authorization->access_code, $serializedResult);
        $this->assertStringNotContainsString($authorization->invitation_token_hash, $serializedResult);
        $this->assertDatabaseCount('visitor_accesses', 0);
        $this->assertSame(VisitorAuthorizationStatus::Active, $authorization->refresh()->status);
    }

    public function test_unknown_code_returns_a_controlled_denial(): void
    {
        $doorman = User::factory()->porteiro()->create();

        $this->actingAs($doorman)
            ->postJson(route('portaria.visitor-authorizations.validate'), [
                'access_code' => 'csa_'.str_repeat('A', 32),
            ])
            ->assertOk()
            ->assertExactJson([
                'allowed' => false,
                'reason' => 'not_found',
                'message' => 'Código de acesso não encontrado.',
                'authorization' => null,
            ]);

        $this->assertDatabaseCount('visitor_accesses', 0);
    }

    public function test_validation_returns_controlled_denials_for_non_liberated_authorizations(): void
    {
        $doorman = User::factory()->porteiro()->create();
        $authorizations = [
            'pending_data' => VisitorAuthorization::factory()->pendingData()->create([
                'access_code' => 'csa_'.str_repeat('P', 32),
            ]),
            'future' => VisitorAuthorization::factory()->future()->create(),
            'expired' => VisitorAuthorization::factory()->expired()->create(),
            'canceled' => VisitorAuthorization::factory()->canceled()->create(),
            'used' => VisitorAuthorization::factory()->used()->create(),
        ];
        $openAccessAuthorization = VisitorAuthorization::factory()->active()->create();
        VisitorAccess::factory()->open()->create([
            'visitor_authorization_id' => $openAccessAuthorization->id,
        ]);
        $authorizations['open_access'] = $openAccessAuthorization;

        foreach ($authorizations as $reason => $authorization) {
            $this->actingAs($doorman)
                ->postJson(route('portaria.visitor-authorizations.validate'), [
                    'access_code' => $authorization->access_code,
                ])
                ->assertOk()
                ->assertJsonPath('allowed', false)
                ->assertJsonPath('reason', $reason)
                ->assertJsonPath('authorization', null);
        }
    }

    public function test_access_code_is_required(): void
    {
        $doorman = User::factory()->porteiro()->create();

        $this->actingAs($doorman)
            ->postJson(route('portaria.visitor-authorizations.validate'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('access_code');
    }

    #[DataProvider('invalidAccessCodes')]
    public function test_validation_and_entry_reject_malformed_access_codes(mixed $accessCode): void
    {
        $this->actingAs(User::factory()->porteiro()->create());

        foreach (['portaria.visitor-authorizations.validate', 'portaria.visitor-accesses.store'] as $routeName) {
            $this->postJson(route($routeName), ['access_code' => $accessCode])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('access_code');
        }

        $this->assertDatabaseCount('visitor_accesses', 0);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidAccessCodes(): array
    {
        return [
            'wrong prefix' => ['abc_'.str_repeat('A', 32)],
            'uppercase prefix' => ['CSA_'.str_repeat('A', 32)],
            'too short' => ['csa_'.str_repeat('A', 31)],
            'too long' => ['csa_'.str_repeat('A', 33)],
            'internal whitespace' => ['csa_'.str_repeat('A', 30).' B'],
            'punctuation' => ['csa_'.str_repeat('A', 31).'_'],
            'non ascii' => ['csa_'.str_repeat('A', 31).'é'],
            'oversized string' => [str_repeat('A', 10000)],
            'empty' => [''],
            'whitespace only' => [" \t\n "],
            'array' => [['csa_'.str_repeat('A', 32)]],
            'integer' => [123],
            'null' => [null],
        ];
    }

    public function test_generated_qr_payload_is_accepted_by_validation_and_entry_with_external_whitespace(): void
    {
        $code = app(VisitorService::class)->generateVisitorCode();
        $authorization = VisitorAuthorization::factory()->active()->create(['access_code' => $code]);
        $qrPayload = app(VisitorQrCodeService::class)->payload($authorization);

        $this->assertMatchesRegularExpression('/\Acsa_[A-Za-z0-9]{32}\z/', $code);
        $this->assertSame($code, $qrPayload);

        $this->actingAs(User::factory()->porteiro()->create())
            ->postJson(route('portaria.visitor-authorizations.validate'), [
                'access_code' => " \t{$qrPayload}\n ",
            ])
            ->assertOk()
            ->assertJsonPath('allowed', true);

        $this->postJson(route('portaria.visitor-accesses.store'), [
            'access_code' => " \t{$qrPayload}\n ",
        ])->assertCreated();

        $this->assertSame($code, $authorization->refresh()->access_code);
        $this->assertSame($authorization->id, VisitorAccess::query()->sole()->visitor_authorization_id);
    }

    public function test_access_code_case_is_preserved(): void
    {
        $code = 'csa_'.str_repeat('aB1', 10).'Z9';
        VisitorAuthorization::factory()->active()->create(['access_code' => $code]);
        $this->actingAs(User::factory()->porteiro()->create());

        $this->postJson(route('portaria.visitor-authorizations.validate'), [
            'access_code' => strtolower($code),
        ])->assertOk()->assertJsonPath('reason', 'not_found');

        $this->postJson(route('portaria.visitor-authorizations.validate'), [
            'access_code' => $code,
        ])->assertOk()->assertJsonPath('allowed', true);
    }

    public function test_guest_cannot_validate_an_authorization(): void
    {
        $this->postJson(route('portaria.visitor-authorizations.validate'), [
            'access_code' => 'csa_qualquer',
        ])->assertUnauthorized();
    }

    public function test_administrator_and_resident_cannot_validate_an_authorization(): void
    {
        foreach ([
            User::factory()->admin()->create(),
            User::factory()->morador()->create(),
        ] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)
                ->postJson(route('portaria.visitor-authorizations.validate'), [
                    'access_code' => 'csa_qualquer',
                ])
                ->assertForbidden();
        }
    }

    public function test_inactive_doorman_cannot_validate_an_authorization(): void
    {
        $inactiveDoorman = User::factory()->porteiro()->inactive()->create();

        $this->actingAs($inactiveDoorman)
            ->postJson(route('portaria.visitor-authorizations.validate'), [
                'access_code' => 'csa_qualquer',
            ])
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_unverified_doorman_cannot_validate_an_authorization(): void
    {
        $unverifiedDoorman = User::factory()->porteiro()->unverified()->create();

        $this->actingAs($unverifiedDoorman)
            ->postJson(route('portaria.visitor-authorizations.validate'), [
                'access_code' => 'csa_qualquer',
            ])
            ->assertForbidden();
    }
}
