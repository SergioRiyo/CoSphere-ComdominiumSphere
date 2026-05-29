<?php

namespace Tests\Feature;

use App\Enums\VisitorAccessStatus;
use App\Enums\VisitorAuthorizationStatus;
use App\Models\Unit;
use App\Models\User;
use App\Models\VisitorAuthorization;
use App\Services\VisitorService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorAccessFeatureTest extends TestCase
{
    use RefreshDatabase;

    private VisitorService $visitorService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->visitorService = app(VisitorService::class);
    }

    public function test_morador_criando_autorizacao_de_visitante(): void
    {
        [$unit, $resident] = $this->createResident();

        $this->actingAs($resident);

        $authorization = $this->visitorService->createVisitorAuthorization([
            'name' => 'Maria Visitante',
            'cpf' => '123.456.789-00',
            'phone' => '(65) 99999-9999',
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
            'start_date' => now()->addHour()->toDateTimeString(),
            'end_date' => now()->addHours(5)->toDateTimeString(),
        ]);

        $this->assertSame($resident->id, $authorization->resident_id);
        $this->assertSame($unit->id, $authorization->unit_id);
        $this->assertSame(VisitorAuthorizationStatus::Active, $authorization->status);
        $this->assertNotEmpty($authorization->access_code);
    }

    public function test_autorizacao_sendo_salva_no_banco(): void
    {
        [$unit, $resident] = $this->createResident();

        $authorization = $this->visitorService->createVisitorAuthorization([
            'name' => 'Joao Visitante',
            'cpf' => '987.654.321-00',
            'phone' => '(65) 98888-8888',
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
            'start_date' => now()->addHour()->toDateTimeString(),
            'end_date' => now()->addHours(4)->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('visitors', [
            'id' => $authorization->visitor_id,
            'name' => 'Joao Visitante',
            'cpf' => '987.654.321-00',
        ]);

        $this->assertDatabaseHas('visitor_authorizations', [
            'id' => $authorization->id,
            'visitor_id' => $authorization->visitor_id,
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
            'status' => VisitorAuthorizationStatus::Active->value,
        ]);
    }

    public function test_porteiro_registrando_entrada(): void
    {
        [$unit, $resident] = $this->createResident();
        $doorman = $this->createDoorman();

        $authorization = $this->visitorService->createVisitorAuthorization([
            'name' => 'Carlos Visitante',
            'cpf' => '111.222.333-44',
            'phone' => '(65) 97777-7777',
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
            'start_date' => now()->subHour()->toDateTimeString(),
            'end_date' => now()->addHours(3)->toDateTimeString(),
        ]);

        $access = $this->visitorService->registerEntry(
            accessCode: $authorization->access_code,
            doormanId: $doorman->id,
            observations: 'Entrada liberada na portaria.',
        );

        $this->assertDatabaseHas('visitor_accesses', [
            'id' => $access->id,
            'visitor_authorization_id' => $authorization->id,
            'doorman_id' => $doorman->id,
            'validation_status' => VisitorAccessStatus::Validated->value,
            'observations' => 'Entrada liberada na portaria.',
        ]);
    }

    public function test_porteiro_registrando_saida(): void
    {
        [$unit, $resident] = $this->createResident();
        $doorman = $this->createDoorman();

        $authorization = $this->visitorService->createVisitorAuthorization([
            'name' => 'Ana Visitante',
            'cpf' => '555.666.777-88',
            'phone' => '(65) 96666-6666',
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
            'start_date' => now()->subHour()->toDateTimeString(),
            'end_date' => now()->addHours(3)->toDateTimeString(),
        ]);

        $this->visitorService->registerEntry(
            accessCode: $authorization->access_code,
            doormanId: $doorman->id,
        );

        $access = $this->visitorService->registerExit(
            accessCode: $authorization->access_code,
            doormanId: $doorman->id,
            observations: 'Saida registrada na portaria.',
        );

        $this->assertNotNull($access->exit_time);

        $this->assertDatabaseHas('visitor_authorizations', [
            'id' => $authorization->id,
            'status' => VisitorAuthorizationStatus::Used->value,
        ]);
    }

    public function test_acesso_negado_para_autorizacao_expirada(): void
    {
        $doorman = $this->createDoorman();
        $authorization = $this->createAuthorization([
            'status' => VisitorAuthorizationStatus::Active,
            'start_date' => now()->subHours(3),
            'end_date' => now()->subHour(),
        ]);

        try {
            $this->visitorService->registerEntry(
                accessCode: $authorization->access_code,
                doormanId: $doorman->id,
            );
            $this->fail('Era esperada uma DomainException para autorizacao expirada.');
        } catch (DomainException $exception) {
            $this->assertSame('Autorização expirada.', $exception->getMessage());
        }

        $this->assertDatabaseHas('visitor_accesses', [
            'visitor_authorization_id' => $authorization->id,
            'doorman_id' => $doorman->id,
            'validation_status' => VisitorAccessStatus::Rejected->value,
        ]);
    }

    public function test_acesso_negado_para_autorizacao_cancelada(): void
    {
        $doorman = $this->createDoorman();
        $authorization = $this->createAuthorization([
            'status' => VisitorAuthorizationStatus::Canceled,
            'start_date' => now()->subHour(),
            'end_date' => now()->addHour(),
        ]);

        try {
            $this->visitorService->registerEntry(
                accessCode: $authorization->access_code,
                doormanId: $doorman->id,
            );
            $this->fail('Era esperada uma DomainException para autorizacao cancelada.');
        } catch (DomainException $exception) {
            $this->assertSame('Autorização cancelada.', $exception->getMessage());
        }

        $this->assertDatabaseHas('visitor_accesses', [
            'visitor_authorization_id' => $authorization->id,
            'doorman_id' => $doorman->id,
            'validation_status' => VisitorAccessStatus::Rejected->value,
        ]);
    }

    /**
     * @return array{0: Unit, 1: User}
     */
    private function createResident(): array
    {
        $unit = Unit::factory()->create();
        $resident = User::factory()->create([
            'unit_id' => $unit->id,
            'role' => 'morador',
        ]);

        return [$unit, $resident];
    }

    private function createDoorman(): User
    {
        return User::factory()->create([
            'role' => 'porteiro',
        ]);
    }

    private function createAuthorization(array $attributes = [])
    {
        return VisitorAuthorization::factory()->create($attributes);
    }
}
