<?php

namespace Tests\Unit;

use App\Enums\VisitorAccessStatus;
use App\Enums\VisitorAuthorizationStatus;
use App\Models\User;
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

        $this->visitorService->registerEntry(
            accessCode: $authorization->access_code,
            doormanId: $doorman->id,
        );

        $access = $this->visitorService->registerExit(
            accessCode: $authorization->access_code,
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
}
