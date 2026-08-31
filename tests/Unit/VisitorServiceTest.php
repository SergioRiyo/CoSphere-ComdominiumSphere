<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Enums\VisitorAccessStatus;
use App\Enums\VisitorAuthorizationStatus;
use App\Models\Unit;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use App\Services\VisitorService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorServiceTest extends TestCase
{
    use RefreshDatabase;

    private VisitorService $visitorService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->visitorService = app(VisitorService::class);
    }

    public function test_deve_validar_autorizacao_ativa(): void
    {
        $authorization = VisitorAuthorization::factory()->create([
            'status' => VisitorAuthorizationStatus::Active,
            'start_date' => now()->subHour(),
            'end_date' => now()->addHour(),
        ]);

        $result = $this->visitorService->validateAuthorization($authorization);

        $this->assertTrue($result->is($authorization));
    }

    public function test_deve_bloquear_autorizacao_expirada(): void
    {
        $authorization = VisitorAuthorization::factory()->create([
            'status' => VisitorAuthorizationStatus::Active,
            'start_date' => now()->subHours(3),
            'end_date' => now()->subHour(),
        ]);

        try {
            $this->visitorService->validateAuthorization($authorization);
            $this->fail('Era esperada uma DomainException para autorização expirada.');
        } catch (DomainException $exception) {
            $this->assertSame('Autorização expirada.', $exception->getMessage());
        }

        $this->assertDatabaseHas('visitor_authorizations', [
            'id' => $authorization->id,
            'status' => VisitorAuthorizationStatus::Expired->value,
        ]);
    }

    public function test_deve_bloquear_autorizacao_cancelada(): void
    {
        $authorization = VisitorAuthorization::factory()->create([
            'status' => VisitorAuthorizationStatus::Canceled,
            'start_date' => now()->subHour(),
            'end_date' => now()->addHour(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Autorização cancelada.');

        $this->visitorService->validateAuthorization($authorization);
    }

    public function test_deve_liberar_acesso_com_autorizacao_valida(): void
    {
        $authorization = VisitorAuthorization::factory()->create([
            'status' => VisitorAuthorizationStatus::Active,
            'start_date' => now()->subHour(),
            'end_date' => now()->addHour(),
        ]);

        $result = $this->visitorService->validateAuthorizationByCode($authorization->access_code);

        $this->assertSame($authorization->id, $result->id);
    }

    public function test_deve_negar_acesso_com_autorizacao_invalida(): void
    {
        $doorman = User::factory()->create();

        $authorization = VisitorAuthorization::factory()->create([
            'status' => VisitorAuthorizationStatus::Canceled,
            'start_date' => now()->subHour(),
            'end_date' => now()->addHour(),
        ]);

        try {
            $this->visitorService->registerEntry(
                accessCode: $authorization->access_code,
                doormanId: $doorman->id,
            );
            $this->fail('Era esperada uma DomainException para autorização cancelada.');
        } catch (DomainException $exception) {
            $this->assertSame('Autorização cancelada.', $exception->getMessage());
        }

        $this->assertDatabaseHas('visitor_accesses', [
            'visitor_authorization_id' => $authorization->id,
            'doorman_id' => $doorman->id,
            'validation_status' => VisitorAccessStatus::Rejected->value,
        ]);
    }

    public function test_deve_registrar_entrada_de_visitante(): void
    {
        $doorman = User::factory()->create();

        $authorization = VisitorAuthorization::factory()->create([
            'status' => VisitorAuthorizationStatus::Active,
            'start_date' => now()->subHour(),
            'end_date' => now()->addHour(),
        ]);

        $access = $this->visitorService->registerEntry(
            accessCode: $authorization->access_code,
            doormanId: $doorman->id,
            observations: 'Entrada autorizada.',
        );

        $this->assertInstanceOf(VisitorAccess::class, $access);

        $this->assertDatabaseHas('visitor_accesses', [
            'id' => $access->id,
            'visitor_authorization_id' => $authorization->id,
            'doorman_id' => $doorman->id,
            'validation_status' => VisitorAccessStatus::Validated->value,
            'observations' => 'Entrada autorizada.',
        ]);

        $this->assertNotNull($access->entry_time);
        $this->assertNull($access->exit_time);
    }

    public function test_deve_registrar_saida_de_visitante(): void
    {
        $doorman = User::factory()->create();

        $authorization = VisitorAuthorization::factory()->create([
            'status' => VisitorAuthorizationStatus::Active,
            'start_date' => now()->subHour(),
            'end_date' => now()->addHour(),
        ]);

        $entryAccess = $this->visitorService->registerEntry(
            accessCode: $authorization->access_code,
            doormanId: $doorman->id,
        );

        $access = $this->visitorService->registerExit(
            visitorAccess: $entryAccess,
            doormanId: $doorman->id,
            observations: 'Saída registrada.',
        );

        $this->assertDatabaseHas('visitor_accesses', [
            'id' => $access->id,
            'visitor_authorization_id' => $authorization->id,
            'doorman_id' => $doorman->id,
            'validation_status' => VisitorAccessStatus::Validated->value,
            'observations' => 'Saída registrada.',
        ]);

        $this->assertNotNull($access->exit_time);

        $this->assertDatabaseHas('visitor_authorizations', [
            'id' => $authorization->id,
            'status' => VisitorAuthorizationStatus::Used->value,
        ]);
    }

    public function test_portaria_validation_returns_only_sanitized_operational_data(): void
    {
        $unit = Unit::factory()->create([
            'block' => 'B',
            'number' => '202',
            'status' => 'active',
        ]);
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $visitor = Visitor::factory()->create([
            'name' => 'Visitante da Portaria',
            'cpf' => '529.982.247-25',
            'phone' => '(65) 98888-7777',
        ]);
        $authorization = VisitorAuthorization::factory()->active()->create([
            'visitor_id' => $visitor->id,
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
            'vehicle_plate' => 'BRA2E19',
            'invitation_token_hash' => hash('sha256', 'token-privado'),
        ]);

        $result = $this->visitorService->validateAccessCode($authorization->access_code);

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['reason']);
        $this->assertSame('Acesso liberado.', $result['message']);
        $this->assertSame([
            'visitor_name',
            'unit',
            'vehicle_plate',
            'start_date',
            'end_date',
            'status',
        ], array_keys($result['authorization']));
        $this->assertSame($visitor->name, $result['authorization']['visitor_name']);
        $this->assertSame(['block' => 'B', 'number' => '202'], $result['authorization']['unit']);
        $this->assertSame('BRA2E19', $result['authorization']['vehicle_plate']);
        $this->assertSame(VisitorAuthorizationStatus::Active->value, $result['authorization']['status']);

        $serializedResult = json_encode($result, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString($visitor->cpf, $serializedResult);
        $this->assertStringNotContainsString($visitor->phone, $serializedResult);
        $this->assertStringNotContainsString($authorization->access_code, $serializedResult);
        $this->assertStringNotContainsString($authorization->invitation_token_hash, $serializedResult);
        $this->assertDatabaseCount('visitor_accesses', 0);
    }

    public function test_portaria_validation_returns_a_controlled_result_for_unknown_code(): void
    {
        $result = $this->visitorService->validateAccessCode('csa_codigo_inexistente');

        $this->assertSame([
            'allowed' => false,
            'reason' => 'not_found',
            'message' => 'Código de acesso não encontrado.',
            'authorization' => null,
        ], $result);
    }

    public function test_portaria_validation_distinguishes_non_liberated_authorizations(): void
    {
        $authorizations = [
            'pending_data' => VisitorAuthorization::factory()->pendingData()->create([
                'access_code' => 'csa_pendente',
            ]),
            'future' => VisitorAuthorization::factory()->future()->create(),
            'expired' => VisitorAuthorization::factory()->expired()->create(),
            'canceled' => VisitorAuthorization::factory()->canceled()->create(),
            'used' => VisitorAuthorization::factory()->used()->create(),
        ];

        foreach ($authorizations as $expectedReason => $authorization) {
            $result = $this->visitorService->validateAccessCode($authorization->access_code);

            $this->assertFalse($result['allowed']);
            $this->assertSame($expectedReason, $result['reason']);
            $this->assertNull($result['authorization']);
        }
    }

    public function test_portaria_validation_persists_expiration_for_an_active_authorization(): void
    {
        $authorization = VisitorAuthorization::factory()->active()->create([
            'start_date' => now()->subHours(2),
            'end_date' => now()->subMinute(),
        ]);

        $result = $this->visitorService->validateAccessCode($authorization->access_code);

        $this->assertFalse($result['allowed']);
        $this->assertSame('expired', $result['reason']);
        $this->assertSame(VisitorAuthorizationStatus::Expired, $authorization->refresh()->status);
        $this->assertDatabaseCount('visitor_accesses', 0);
    }

    public function test_portaria_validation_rejects_an_authorization_with_open_access(): void
    {
        $authorization = VisitorAuthorization::factory()->active()->create();
        VisitorAccess::factory()->open()->create([
            'visitor_authorization_id' => $authorization->id,
        ]);

        $result = $this->visitorService->validateAccessCode($authorization->access_code);

        $this->assertFalse($result['allowed']);
        $this->assertSame('open_access', $result['reason']);
        $this->assertSame(
            'Este visitante já possui uma entrada registrada sem saída.',
            $result['message'],
        );
        $this->assertSame(1, $authorization->visitorAccesses()->count());
    }

    public function test_portaria_validation_rejects_invalid_resident_and_unit_integrity(): void
    {
        $activeUnit = Unit::factory()->create(['status' => 'active']);
        $otherUnit = Unit::factory()->create(['status' => 'active']);
        $inactiveUnit = Unit::factory()->create(['status' => 'inactive']);

        $inactiveResident = User::factory()->morador()->inactive()->create([
            'unit_id' => $activeUnit->id,
        ]);
        $wrongRoleResident = User::factory()->create([
            'unit_id' => $activeUnit->id,
            'role' => UserRole::Admin,
        ]);
        $residentFromOtherUnit = User::factory()->morador()->create([
            'unit_id' => $otherUnit->id,
        ]);
        $residentFromInactiveUnit = User::factory()->morador()->create([
            'unit_id' => $inactiveUnit->id,
        ]);

        $authorizations = [
            VisitorAuthorization::factory()->active()->create([
                'unit_id' => $activeUnit->id,
                'resident_id' => $inactiveResident->id,
            ]),
            VisitorAuthorization::factory()->active()->create([
                'unit_id' => $activeUnit->id,
                'resident_id' => $wrongRoleResident->id,
            ]),
            VisitorAuthorization::factory()->active()->create([
                'unit_id' => $activeUnit->id,
                'resident_id' => $residentFromOtherUnit->id,
            ]),
            VisitorAuthorization::factory()->active()->create([
                'unit_id' => $inactiveUnit->id,
                'resident_id' => $residentFromInactiveUnit->id,
            ]),
        ];

        foreach ($authorizations as $authorization) {
            $result = $this->visitorService->validateAccessCode($authorization->access_code);

            $this->assertFalse($result['allowed']);
            $this->assertSame('inconsistent_authorization', $result['reason']);
            $this->assertSame('Os dados da autorização estão inconsistentes.', $result['message']);
            $this->assertNull($result['authorization']);
        }
    }
}
