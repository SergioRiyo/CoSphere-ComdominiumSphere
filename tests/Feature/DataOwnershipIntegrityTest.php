<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Vehicle;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataOwnershipIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_and_vehicle_factories_match_the_owner_unit(): void
    {
        $reservation = Reservation::factory()->create()->load('user');
        $vehicle = Vehicle::factory()->create()->load('user');

        $this->assertSame(UserRole::Morador, $reservation->user->role);
        $this->assertSame($reservation->unit_id, $reservation->user->unit_id);

        $this->assertSame(UserRole::Morador, $vehicle->user->role);
        $this->assertSame($vehicle->unit_id, $vehicle->user->unit_id);
    }

    public function test_resident_owned_factories_match_the_resident_unit(): void
    {
        $authorization = VisitorAuthorization::factory()->create()->load('resident');
        $order = Order::factory()->create()->load('resident');
        $incident = Incident::factory()->create()->load('resident');

        $this->assertSame(UserRole::Morador, $authorization->resident->role);
        $this->assertSame($authorization->unit_id, $authorization->resident->unit_id);

        $this->assertSame(UserRole::Morador, $order->resident->role);
        $this->assertSame($order->unit_id, $order->resident->unit_id);

        $this->assertSame(UserRole::Morador, $incident->resident->role);
        $this->assertSame($incident->unit_id, $incident->resident->unit_id);
    }

    public function test_factories_assign_staff_roles_without_units(): void
    {
        $visitorAccess = VisitorAccess::factory()
            ->create()
            ->load(['visitorAuthorization.resident', 'doorman']);
        $maintenanceRequest = MaintenanceRequest::factory()
            ->create()
            ->load(['incident.resident', 'admin']);

        $authorization = $visitorAccess->visitorAuthorization;
        $this->assertSame(UserRole::Morador, $authorization->resident->role);
        $this->assertSame($authorization->unit_id, $authorization->resident->unit_id);
        $this->assertSame(UserRole::Porteiro, $visitorAccess->doorman->role);
        $this->assertNull($visitorAccess->doorman->unit_id);

        $incident = $maintenanceRequest->incident;
        $this->assertSame(UserRole::Morador, $incident->resident->role);
        $this->assertSame($incident->unit_id, $incident->resident->unit_id);
        $this->assertSame(UserRole::Admin, $maintenanceRequest->admin->role);
        $this->assertNull($maintenanceRequest->admin->unit_id);
    }
}
