<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Services\MaintenanceRequestQueryService;
use App\Services\MaintenanceRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaintenanceQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_combined_filters_paginate_with_stable_order_and_inclusive_dates(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = Incident::factory()->create();
        $provider = ServiceProvider::factory()->create();
        $attributes = ['incident_id' => $incident->id, 'unit_id' => $incident->unit_id, 'resident_id' => $incident->resident_id, 'admin_id' => $admin->id, 'service_provider_id' => $provider->id, 'status' => 'scheduled', 'created_at' => '2026-10-01 23:59:59'];
        $items = MaintenanceRequest::factory()->count(17)->create($attributes);
        foreach (['admin_id' => User::factory()->admin()->create()->id, 'unit_id' => User::factory()->morador()->create()->unit_id, 'service_provider_id' => ServiceProvider::factory()->create()->id, 'status' => 'pending', 'incident_id' => null, 'created_at' => '2026-10-02 00:00:00'] as $field => $value) {
            MaintenanceRequest::factory()->create(array_replace($attributes, [$field => $value]));
        }
        $filters = ['admin_id' => $admin->id, 'unit_id' => $incident->unit_id, 'service_provider_id' => $provider->id, 'status' => 'scheduled', 'link' => 'linked', 'date_from' => '2026-10-01', 'date_to' => '2026-10-01'];
        $this->actingAs($admin)->get(route('admin.maintenances.index', $filters))->assertInertia(fn (Assert $page) => $page->component('admin/maintenances')->where('maintenances.total', 17)->has('maintenances.data', 15)->where('maintenances.data.0.id', $items->last()->id)->missing('maintenances.data.0.history')->missing('maintenances.data.0.resident_id'));
        $this->get(route('admin.maintenances.index', $filters + ['page' => 2]))->assertInertia(fn (Assert $page) => $page->has('maintenances.data', 2)->where('filters.status', 'scheduled')->where('filters.date_to', '2026-10-01'));
        $paginator = app(MaintenanceRequestQueryService::class)->paginate($admin, $filters);
        $this->assertStringContainsString('status=scheduled', $paginator->url(2));
        $this->assertStringContainsString('date_to=2026-10-01', $paginator->url(2));
    }

    public function test_resident_receives_only_authorized_history_and_cannot_access_neighbor_or_admin_detail(): void
    {
        $incident = Incident::factory()->create();
        $neighbor = User::factory()->morador()->create(['unit_id' => $incident->unit_id]);
        $admin = User::factory()->admin()->create();
        $service = app(MaintenanceRequestService::class);
        $maintenance = $service->createFromIncident($admin, $incident);
        $provider = ServiceProvider::factory()->create();
        $service->update($admin, $maintenance, ['description' => 'Descrição pública', 'cost' => '700.00', 'admin_id' => User::factory()->admin()->create()->id, 'service_provider_id' => $provider->id, 'scheduled_at' => now()->addDay()->toDateTimeString()]);
        $provider->delete();
        $this->actingAs($incident->resident)->get(route('morador.incidents.show', $incident))->assertInertia(fn (Assert $page) => $page
            ->where('incident.maintenance_requests.0.description', 'Descrição pública')->where('incident.maintenance_requests.0.provider', $provider->name)
            ->has('incident.maintenance_requests.0.history', 2)->missing('incident.maintenance_requests.0.cost')->missing('incident.maintenance_requests.0.admin_id')
            ->missing('incident.maintenance_requests.0.history.1.changes.cost')->missing('incident.maintenance_requests.0.history.1.changes.admin_id'));
        $this->get(route('admin.maintenances.show', $maintenance))->assertForbidden();
        foreach ([$neighbor, User::factory()->morador()->create()] as $other) {
            $this->actingAs($other)->get(route('morador.incidents.show', $incident))->assertNotFound();
        }
    }

    #[DataProvider('invalidFilters')]
    public function test_filters_are_validated(array $filters): void
    {
        $this->actingAs(User::factory()->admin()->create())->getJson(route('admin.maintenances.index', $filters))->assertUnprocessable();
    }

    public static function invalidFilters(): array
    {
        return [[['status' => 'unknown']], [['unit_id' => 99999]], [['admin_id' => 99999]], [['service_provider_id' => 99999]], [['link' => 'unknown']], [['date_from' => 'no-date']], [['date_to' => '2026-10-01', 'date_from' => '2026-10-02']], [['page' => 0]]];
    }

    public function test_list_query_count_is_bounded_without_loading_histories(): void
    {
        $admin = User::factory()->admin()->create();
        MaintenanceRequest::factory()->linkedToIncident()->scheduled()->create();
        DB::enableQueryLog();
        $query = app(MaintenanceRequestQueryService::class);
        $query->paginate($admin, []);
        $one = DB::getQueryLog();
        DB::disableQueryLog();
        MaintenanceRequest::factory()->count(14)->linkedToIncident()->scheduled()->create();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $result = $query->paginate($admin, []);
        $many = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(count($one), $many);
        $this->assertLessThanOrEqual(6, count($many));
        $this->assertCount(15, $result->items());
        $this->assertStringNotContainsString('histories', implode(' ', array_column($many, 'query')));
        $this->assertStringNotContainsString('maintenance_request_changes', implode(' ', array_column($many, 'query')));
    }
}
