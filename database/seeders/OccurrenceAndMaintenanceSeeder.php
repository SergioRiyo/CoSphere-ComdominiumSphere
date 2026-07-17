<?php

namespace Database\Seeders;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\MaintenanceRequestStatus;
use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\ServiceProvider;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class OccurrenceAndMaintenanceSeeder extends Seeder
{
    public function run(): void
    {
        $units = Unit::query()->take(5)->get();
        $users = User::query()->take(5)->get();

        if ($units->isEmpty() || $users->isEmpty()) {
            return;
        }

        ServiceProvider::factory()
            ->count(8)
            ->create();

        foreach ($units as $unit) {
            Incident::factory()
                ->count(2)
                ->create([
                    'unit_id' => $unit->id,
                    'resident_id' => $users->random()->id,
                ]);

            Incident::factory()
                ->create([
                    'unit_id' => $unit->id,
                    'resident_id' => $users->random()->id,
                    'category' => 'maintenance',
                    'status' => IncidentStatus::InProgress->value,
                    'priority' => IncidentPriority::High->value,
                    'title' => 'Solicitação de manutenção',
                    'description' => 'Morador solicitou manutenção em área vinculada à unidade.',
                ]);
        }

        $maintenanceIncidents = Incident::query()
            ->where('category', 'maintenance')
            ->take(5)
            ->get();

        foreach ($maintenanceIncidents as $incident) {
            MaintenanceRequest::factory()
                ->create([
                    'incident_id' => $incident->id,
                    'service_provider_id' => ServiceProvider::query()
                        ->inRandomOrder()
                        ->value('id'),
                    'admin_id' => $users->random()->id,
                    'status' => MaintenanceRequestStatus::Scheduled->value,
                ]);
        }
    }
}
