<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Services\MaintenanceRequestQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ServiceProviderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_registers_edits_normalizes_and_can_keep_own_document(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.service-providers.store'), ['name' => 'Oficina', 'cpf_cnpj' => '12.345.678/0001-90', 'phone' => '(65) 3333-0000', 'email' => 'oficina@example.test', 'specialty' => 'Portões'])->assertRedirect();
        $provider = ServiceProvider::sole();
        $this->assertSame('12345678000190', $provider->cpf_cnpj);
        $this->get(route('admin.service-providers.show', $provider))->assertInertia(fn (Assert $page) => $page->component('admin/service-provider-form')->where('provider.name', 'Oficina'));
        $this->patch(route('admin.service-providers.update', $provider), ['name' => 'Oficina atualizada', 'cpf_cnpj' => '12.345.678/0001-90'])->assertRedirect();
        $this->assertSame('Oficina atualizada', $provider->fresh()->name);
        foreach ([null, ''] as $document) {
            $this->post(route('admin.service-providers.store'), ['name' => 'Sem documento', 'cpf_cnpj' => $document])->assertRedirect();
        }
        $this->assertSame(2, ServiceProvider::whereNull('cpf_cnpj')->count());
        $this->get(route('admin.service-providers.index', ['search' => 'atualizada']))->assertInertia(fn (Assert $page) => $page->has('providers.data', 1));
    }

    public function test_duplicate_identity_is_rejected_including_masked_and_archived_records(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = ServiceProvider::factory()->create(['cpf_cnpj' => '11144477735']);
        $other = ServiceProvider::factory()->create();
        $this->actingAs($admin);
        foreach ([false, true] as $archived) {
            if ($archived) {
                $provider->delete();
            }
            foreach (['111.444.777-35', '11144477735'] as $document) {
                $this->postJson(route('admin.service-providers.store'), ['name' => 'Duplicado', 'cpf_cnpj' => $document])->assertUnprocessable()->assertJsonValidationErrors('cpf_cnpj');
                $this->patchJson(route('admin.service-providers.update', $other), ['name' => 'Duplicado', 'cpf_cnpj' => $document])->assertUnprocessable()->assertJsonValidationErrors('cpf_cnpj');
            }
        }
        $this->assertSame(2, ServiceProvider::withTrashed()->count());
    }

    #[DataProvider('invalidDocuments')]
    public function test_invalid_document_format_is_rejected(mixed $document): void
    {
        $this->actingAs(User::factory()->admin()->create())->postJson(route('admin.service-providers.store'), ['name' => 'Prestador', 'cpf_cnpj' => $document])->assertUnprocessable()->assertJsonValidationErrors('cpf_cnpj');
        $this->assertDatabaseCount('service_providers', 0);
    }

    public static function invalidDocuments(): array
    {
        return [['123'], ['abc11144477735'], ['111444777351111'], [['11144477735']]];
    }

    public function test_archive_preserves_history_and_excludes_provider_from_new_assignments(): void
    {
        $admin = User::factory()->admin()->create();
        $maintenance = MaintenanceRequest::factory()->inProgress()->create();
        $provider = $maintenance->serviceProvider;
        $before = $maintenance->refresh()->getRawOriginal();
        $this->actingAs($admin)->patch(route('admin.service-providers.archive', $provider))->assertRedirect();
        $this->assertSoftDeleted($provider);
        $this->assertSame($before, $maintenance->fresh()->getRawOriginal());
        $this->assertSame($provider->name, $maintenance->fresh()->serviceProvider->name);
        $this->assertNotContains((string) $provider->id, array_column(app(MaintenanceRequestQueryService::class)->options($admin)['providers'], 'value'));
        $this->get(route('admin.service-providers.show', $provider))->assertOk();
        $this->get(route('admin.service-providers.index', ['state' => 'archived']))->assertInertia(fn (Assert $page) => $page->has('providers.data', 1));
        $pending = MaintenanceRequest::factory()->create();
        $this->patchJson(route('admin.maintenances.update', $pending), ['service_provider_id' => $provider->id])->assertUnprocessable();
        $this->get(route('admin.maintenances.show', $maintenance))->assertInertia(fn (Assert $page) => $page->where('maintenance.provider', $provider->name)->where('maintenance.provider_archived', true));
        $this->patch(route('admin.maintenances.update', $maintenance), ['status' => 'completed'])->assertRedirect();
        $this->assertSame('completed', $maintenance->fresh()->status->value);
    }

    #[DataProvider('roles')]
    public function test_resident_and_doorman_cannot_manage_providers(string $role): void
    {
        $provider = ServiceProvider::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->get(route('admin.service-providers.index'))->assertForbidden();
        $this->get(route('admin.service-providers.create'))->assertForbidden();
        $this->get(route('admin.service-providers.show', $provider))->assertForbidden();
        $this->postJson(route('admin.service-providers.store'), ['name' => 'Indevido'])->assertForbidden();
        $this->patchJson(route('admin.service-providers.update', $provider), ['name' => 'Indevido'])->assertForbidden();
        $this->patchJson(route('admin.service-providers.archive', $provider))->assertForbidden();
    }

    public static function roles(): array
    {
        return [['morador'], ['porteiro']];
    }
}
