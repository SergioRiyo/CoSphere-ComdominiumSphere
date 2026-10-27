<?php

namespace Tests\Feature;

use App\Enums\MaintenanceRequestStatus;
use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceRequestFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_pending_independent_maintenance_without_provider(): void
    {
        $request = MaintenanceRequest::factory()->create();
        $this->assertNull($request->incident_id);
        $this->assertNull($request->incident);
        $this->assertNull($request->service_provider_id);
        $this->assertNull($request->serviceProvider);
        $this->assertNull($request->admin);
        $this->assertSame(MaintenanceRequestStatus::Pending, $request->status);
        $this->assertSame(UserRole::Morador, $request->resident->role);
        $this->assertSame($request->unit_id, $request->resident->unit_id);
        $this->assertSame($request->unit_id, $request->unit->id);
        $this->assertNull($request->scheduled_at);
        $this->assertNull($request->executed_at);
        $this->assertNull($request->cost);
        $this->assertDatabaseCount('incidents', 0);
        $this->assertDatabaseCount('service_providers', 0);
        $this->assertModelExists($request);
    }

    public function test_factory_preserves_all_valid_relations_when_linked_and_assigned(): void
    {
        $incident = Incident::factory()->create();
        $request = MaintenanceRequest::factory()->linkedToIncident($incident)->scheduled()->create();
        $this->assertSame($incident->id, $request->incident->id);
        $this->assertSame($incident->resident_id, $request->resident_id);
        $this->assertSame($incident->unit_id, $request->unit_id);
        $this->assertNotNull($request->serviceProvider);
        $this->assertSame(UserRole::Admin, $request->admin->role);
        $this->assertNull($request->admin->unit_id);
        $this->assertSame($request->id, $request->serviceProvider->maintenanceRequests()->sole()->id);
        $this->assertSame($request->id, $request->admin->managedMaintenanceRequests()->sole()->id);
        $this->assertSame($request->id, $incident->maintenanceRequests()->sole()->id);
    }

    public function test_named_states_keep_status_dates_assignment_and_cost_coherent(): void
    {
        foreach ([
            'pendingWithoutProvider' => MaintenanceRequestStatus::Pending,
            'scheduled' => MaintenanceRequestStatus::Scheduled,
            'inProgress' => MaintenanceRequestStatus::InProgress,
            'completed' => MaintenanceRequestStatus::Completed,
            'canceled' => MaintenanceRequestStatus::Canceled,
        ] as $state => $status) {
            $request = MaintenanceRequest::factory()->{$state}()->create()->refresh();
            $this->assertSame($status, $request->status);
            $this->assertSame($request->unit_id, $request->resident->unit_id);
            if (in_array($status, [MaintenanceRequestStatus::Pending, MaintenanceRequestStatus::Canceled], true)) {
                $this->assertNull($request->scheduled_at);
                $this->assertNull($request->service_provider_id);
            } else {
                $this->assertNotNull($request->serviceProvider);
                $this->assertTrue($request->scheduled_at->greaterThanOrEqualTo($request->created_at));
            }
            if ($status === MaintenanceRequestStatus::Completed) {
                $this->assertTrue($request->executed_at->greaterThanOrEqualTo($request->scheduled_at));
                $this->assertTrue($request->executed_at->lessThanOrEqualTo(now()));
                $this->assertIsString($request->cost);
            } else {
                $this->assertNull($request->executed_at);
                $this->assertNull($request->cost);
            }
        }
        $resident = User::factory()->morador()->create();
        $associated = MaintenanceRequest::factory()->for($resident, 'resident')->create();
        $this->assertSame($resident->unit_id, $associated->unit_id);
        $independent = MaintenanceRequest::factory()->withoutIncident()->create(['resident_id' => $resident->id]);
        $this->assertNull($independent->incident);
        $this->assertSame($resident->unit_id, $independent->unit_id);
        $linked = MaintenanceRequest::factory()->linkedToIncident()->pendingWithoutProvider()->create();
        $this->assertNotNull($linked->incident);
        $this->assertNull($linked->serviceProvider);
    }
}
