<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Services\IncidentQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IncidentQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_resident_scope_is_individual_even_after_moving_units_and_pagination_preserves_combined_filters(): void
    {
        $resident = User::factory()->morador()->create();
        $sameUnit = User::factory()->morador()->create(['unit_id' => $resident->unit_id]);
        $attributes = ['resident_id' => $resident->id, 'type' => 'maintenance_request', 'category' => 'security', 'status' => 'in_progress', 'created_at' => '2026-10-01 10:00:00'];
        $own = Incident::factory()->count(20)->create($attributes);
        Incident::factory()->create(array_replace($attributes, ['resident_id' => $sameUnit->id]));
        Incident::factory()->create(array_replace($attributes, ['resident_id' => User::factory()->morador()->create()->id]));
        Incident::factory()->create(array_replace($attributes, ['status' => 'open']));
        Incident::factory()->create(array_replace($attributes, ['type' => 'incident']));
        Incident::factory()->create(array_replace($attributes, ['category' => 'noise']));
        Incident::factory()->create(array_replace($attributes, ['created_at' => '2026-09-30 23:59:59']));
        $resident->update(['unit_id' => User::factory()->morador()->create()->unit_id]);
        $filters = ['type' => 'maintenance_request', 'category' => 'security', 'status' => 'in_progress', 'date_from' => '2026-10-01', 'date_to' => '2026-10-01'];
        $this->actingAs($resident)->get(route('morador.incidents.index', $filters))->assertInertia(fn (Assert $page) => $page
            ->component('morador/incidents')->where('incidents.total', 20)->has('incidents.data', 15)
            ->where('incidents.data.0.id', $own->last()->id)
            ->where('incidents.next_page_url', function (string $url) use ($filters): bool {
                parse_str(parse_url($url, PHP_URL_QUERY), $actual);
                $this->assertSame($filters + ['page' => '2'], $actual);

                return true;
            }));
        $this->get(route('morador.incidents.index', $filters + ['page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->where('incidents.total', 20)->has('incidents.data', 5)->where('incidents.current_page', 2));
    }

    #[DataProvider('filterFields')]
    public function test_admin_filters_each_supported_field(string $field): void
    {
        $incident = Incident::factory()->create([
            'type' => 'maintenance_request', 'category' => 'noise', 'priority' => 'high', 'status' => 'in_progress',
            'created_at' => '2026-10-02 12:00:00',
        ]);
        $other = Incident::factory()->create([
            'type' => 'incident', 'category' => 'security', 'priority' => 'low', 'status' => 'open',
            'created_at' => $field === 'date_to' ? '2026-10-03 00:00:00' : '2026-10-01 23:59:59',
        ]);
        $filter = match ($field) {
            'date_from', 'date_to' => '2026-10-02',
            default => $incident->getRawOriginal($field),
        };
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.incidents.index', [$field => $filter]))
            ->assertInertia(fn (Assert $page) => $page->component('admin/incidents')->where('incidents.total', 1)
                ->where('incidents.data.0.id', $incident->id)->where('incidents.data.0.resident', $incident->resident->name));
        $this->assertNotSame($incident->id, $other->id);
    }

    public static function filterFields(): array
    {
        return array_map(fn (string $field): array => [$field], ['resident_id', 'unit_id', 'type', 'category', 'priority', 'status', 'date_from', 'date_to']);
    }

    public function test_admin_combines_filters_and_period_is_inclusive_for_entire_final_day(): void
    {
        $incident = Incident::factory()->create([
            'type' => 'maintenance_request', 'category' => 'noise', 'priority' => 'high', 'status' => 'in_progress',
            'created_at' => '2026-10-02 23:59:59',
        ]);
        $filters = $incident->only(['resident_id', 'unit_id']) + [
            'type' => 'maintenance_request', 'category' => 'noise', 'priority' => 'high', 'status' => 'in_progress',
            'date_from' => '2026-10-02', 'date_to' => '2026-10-02',
        ];
        $base = $incident->getRawOriginal();
        unset($base['id']);
        Incident::factory()->create(array_replace($base, ['created_at' => '2026-10-03 00:00:00']));
        Incident::factory()->create(array_replace($base, ['created_at' => '2026-10-02 00:00:00']));
        Incident::factory()->create(array_replace($base, ['priority' => 'low']));
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.incidents.index', $filters))
            ->assertInertia(fn (Assert $page) => $page->where('incidents.total', 2)->where('incidents.data.0.id', $incident->id));
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filter_values_are_rejected(string $field, mixed $value): void
    {
        $this->actingAs(User::factory()->admin()->create())->getJson(route('admin.incidents.index', [$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function invalidFilters(): array
    {
        return [
            ['type', 'fake'], ['category', 'unknown'], ['status', 'fake'], ['priority', 'urgent'],
            ['resident_id', 999999], ['unit_id', 999999], ['page', 0], ['page', 'x'],
            ['date_from', '2026-02-30'], ['date_to', 'not-a-date'], ['type', ['incident']],
        ];
    }

    public function test_invalid_date_order_and_resident_administrative_filters_are_rejected(): void
    {
        $this->actingAs(User::factory()->morador()->create());
        $this->getJson(route('morador.incidents.index', ['date_from' => '2026-10-03', 'date_to' => '2026-10-02']))
            ->assertUnprocessable()->assertJsonValidationErrors('date_to');
        $this->getJson(route('morador.incidents.index', ['resident_id' => 1, 'unit_id' => 1, 'priority' => 'high']))
            ->assertUnprocessable()->assertJsonValidationErrors(['resident_id', 'unit_id', 'priority']);
    }

    public function test_legacy_categories_remain_readable_filterable_and_scoped(): void
    {
        $incident = Incident::factory()->create(['category' => 'legacy/electrical']);
        $other = Incident::factory()->create(['category' => 'private-other-legacy']);
        $this->actingAs($incident->resident)->get(route('morador.incidents.index', ['category' => 'legacy/electrical']))
            ->assertInertia(fn (Assert $page) => $page->where('incidents.total', 1)
                ->where('incidents.data.0.category_label', 'legacy/electrical')
                ->where('options.categories', fn ($options): bool => ! collect($options)->contains('value', 'private-other-legacy')));
        $this->get(route('morador.incidents.show', $incident))->assertOk();
        $this->getJson(route('morador.incidents.index', ['category' => $other->getRawOriginal('category')]))
            ->assertUnprocessable();
    }

    public function test_detail_payload_excludes_provider_pii_and_supports_existing_multiple_links(): void
    {
        $incident = Incident::factory()->create();
        $provider = ServiceProvider::factory()->create();
        MaintenanceRequest::factory()->count(2)->for($incident)->scheduled()->create(['service_provider_id' => $provider->id]);
        $payload = app(IncidentQueryService::class)->details($incident->resident, $incident->id);
        $this->assertCount(2, $payload['maintenance_requests']);
        $this->assertSame($provider->name, $payload['maintenance_requests'][0]['provider']);
        foreach ([$provider->email, $provider->phone, $provider->cpf_cnpj, $incident->resident->email] as $private) {
            $this->assertStringNotContainsString($private, json_encode($payload, JSON_THROW_ON_ERROR));
        }
        $this->assertFalse($payload['can_create_maintenance']);
        $this->assertFalse($payload['can_update_priority']);
        $this->assertSame([], $payload['allowed_statuses']);
    }

    public function test_list_queries_are_bounded_and_do_not_load_full_history_or_description(): void
    {
        $admin = User::factory()->admin()->create();
        Incident::factory()->create();
        $service = app(IncidentQueryService::class);
        DB::enableQueryLog();
        $service->paginate($admin, []);
        $singleQueries = count(DB::getQueryLog());
        DB::disableQueryLog();
        Incident::factory()->count(14)->create();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $result = $service->paginate($admin, []);
        $multipleQueries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame($singleQueries, $multipleQueries);
        $this->assertLessThanOrEqual(4, $multipleQueries);
        $this->assertCount(15, $result->items());
        $this->assertArrayNotHasKey('history', $result->items()[0]);
        $this->assertArrayNotHasKey('description', $result->items()[0]);
        $this->assertArrayNotHasKey('email', $result->items()[0]);
    }
}
