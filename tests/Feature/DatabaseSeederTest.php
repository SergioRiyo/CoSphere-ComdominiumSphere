<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\Order;
use App\Models\Unit;
use App\Models\User;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use Database\Seeders\VisitorAccessSeeder;
use Database\Seeders\VisitorAuthorizationSeeder;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    public function test_database_seeder_creates_core_roles_and_structures(): void
    {
        $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true]);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@cosphere.test',
            'cpf' => '529.982.247-25',
            'phone' => '(65) 90000-0001',
            'role' => 'admin',
            'unit_id' => null,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'morador@cosphere.test',
            'cpf' => '111.444.777-35',
            'phone' => '(65) 90000-0002',
            'role' => 'morador',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'porteiro@cosphere.test',
            'cpf' => '123.456.789-09',
            'phone' => '(65) 90000-0003',
            'role' => 'porteiro',
            'unit_id' => null,
            'is_active' => true,
        ]);

        $this->assertTrue(Unit::query()->exists());
        $this->assertNotNull(User::query()->where('email', 'morador@cosphere.test')->value('unit_id'));

        $this->artisan('db:seed', [
            '--class' => VisitorAuthorizationSeeder::class,
            '--force' => true,
        ])->assertSuccessful();
        $this->artisan('db:seed', [
            '--class' => VisitorAccessSeeder::class,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSeededOwnershipIntegrity();
    }

    private function assertSeededOwnershipIntegrity(): void
    {
        $this->assertTrue(VisitorAuthorization::query()->exists());
        $this->assertTrue(VisitorAccess::query()->exists());
        $this->assertTrue(Order::query()->exists());
        $this->assertTrue(Incident::query()->exists());
        $this->assertTrue(MaintenanceRequest::query()->exists());

        foreach (VisitorAuthorization::query()->with('resident')->cursor() as $authorization) {
            $this->assertSame(UserRole::Morador, $authorization->resident->role);
            $this->assertSame($authorization->unit_id, $authorization->resident->unit_id);
        }

        foreach (VisitorAccess::query()->with(['visitorAuthorization.resident', 'doorman', 'exitDoorman'])->cursor() as $access) {
            $authorization = $access->visitorAuthorization;

            $this->assertSame($authorization->unit_id, $authorization->resident->unit_id);
            $this->assertSame(UserRole::Porteiro, $access->doorman->role);
            $this->assertNull($access->doorman->unit_id);

            if ($access->exitDoorman) {
                $this->assertSame(UserRole::Porteiro, $access->exitDoorman->role);
                $this->assertNull($access->exitDoorman->unit_id);
            }
        }

        foreach (Order::query()->with('resident')->cursor() as $order) {
            $this->assertSame(UserRole::Morador, $order->resident->role);
            $this->assertSame($order->unit_id, $order->resident->unit_id);
        }

        foreach (Incident::query()->with('resident')->cursor() as $incident) {
            $this->assertSame(UserRole::Morador, $incident->resident->role);
            $this->assertSame($incident->unit_id, $incident->resident->unit_id);
        }

        foreach (MaintenanceRequest::query()->with(['incident.resident', 'admin'])->cursor() as $request) {
            $this->assertSame($request->incident->unit_id, $request->incident->resident->unit_id);
            $this->assertSame(UserRole::Admin, $request->admin->role);
            $this->assertNull($request->admin->unit_id);
        }
    }
}
