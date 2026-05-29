<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceRequestFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_deve_criar_solicitacao_de_manutencao_com_relacoes_validas(): void
    {
        $maintenanceRequest = MaintenanceRequest::factory()->create();

        $this->assertNotNull($maintenanceRequest->incident_id);
        $this->assertNotNull($maintenanceRequest->service_provider_id);
        $this->assertNotNull($maintenanceRequest->admin_id);
        $this->assertNotNull($maintenanceRequest->incident);
        $this->assertNotNull($maintenanceRequest->serviceProvider);
        $this->assertNotNull($maintenanceRequest->admin);

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $maintenanceRequest->id,
            'incident_id' => $maintenanceRequest->incident_id,
            'service_provider_id' => $maintenanceRequest->service_provider_id,
            'admin_id' => $maintenanceRequest->admin_id,
        ]);
    }
}
