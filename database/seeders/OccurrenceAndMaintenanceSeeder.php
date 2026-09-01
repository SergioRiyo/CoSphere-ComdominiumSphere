<?php

namespace Database\Seeders;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\MaintenanceRequestStatus;
use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\ServiceProvider;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Seeder;

class OccurrenceAndMaintenanceSeeder extends Seeder
{
    public function run(): void
    {
        if (Incident::query()->exists()
            || MaintenanceRequest::query()->exists()
            || ServiceProvider::query()->exists()) {
            return;
        }

        $units = Unit::query()
            ->whereHas('users', function (Builder $query): void {
                $query
                    ->where('role', UserRole::Morador->value)
                    ->whereNotNull('unit_id');
            })
            ->with(['users' => function (HasMany $query): void {
                $query
                    ->where('role', UserRole::Morador->value)
                    ->whereNotNull('unit_id');
            }])
            ->take(5)
            ->get();
        $admins = User::query()
            ->where('role', UserRole::Admin->value)
            ->whereNull('unit_id')
            ->get();

        if ($units->isEmpty() || $admins->isEmpty()) {
            return;
        }

        ServiceProvider::factory()
            ->count(3)
            ->create();

        $maintenanceIncidentIds = [];

        foreach ($units as $unit) {
            $resident = $unit->users->random();

            Incident::factory()
                ->count(2)
                ->create([
                    'unit_id' => $unit->id,
                    'resident_id' => $resident->id,
                ]);

            $maintenanceIncident = Incident::factory()
                ->create([
                    'unit_id' => $unit->id,
                    'resident_id' => $resident->id,
                    'category' => 'maintenance',
                    'status' => IncidentStatus::InProgress->value,
                    'priority' => IncidentPriority::High->value,
                    'title' => 'Solicitação de manutenção',
                    'description' => 'Morador solicitou manutenção em área vinculada à unidade.',
                ]);

            $maintenanceIncidentIds[] = $maintenanceIncident->id;
        }

        $maintenanceIncidents = Incident::query()
            ->whereKey($maintenanceIncidentIds)
            ->get();

        foreach ($maintenanceIncidents as $incident) {
            MaintenanceRequest::factory()
                ->create([
                    'incident_id' => $incident->id,
                    'service_provider_id' => ServiceProvider::query()
                        ->inRandomOrder()
                        ->value('id'),
                    'admin_id' => $admins->random()->id,
                    'status' => MaintenanceRequestStatus::Scheduled->value,
                ]);
        }
    }
}
