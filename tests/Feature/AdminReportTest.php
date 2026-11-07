<?php

namespace Tests\Feature;

use App\Enums\IncidentStatus;
use App\Enums\MaintenanceRequestStatus;
use App\Enums\ReservationStatus;
use App\Models\CommonArea;
use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\Reservation;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Services\ReportQueryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[DataProvider('kinds')]
    public function test_all_real_statuses_and_totals_are_independent_of_pagination(string $kind): void
    {
        $admin = User::factory()->admin()->create();
        $cases = match ($kind) {
            'reservations' => ReservationStatus::cases(), 'incidents' => IncidentStatus::cases(), 'maintenances' => MaintenanceRequestStatus::cases()
        };
        foreach ($cases as $status) {
            $this->record($kind, ['status' => $status->value]);
        }
        for ($i = 0; $i < 17; $i++) {
            $last = $this->record($kind);
        }
        $total = count($cases) + 17;
        $this->actingAs($admin)->get(route('admin.reports.'.$kind))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports')->where('kind', $kind)->where('metrics.total', $total)->where('records.total', $total)
            ->has('records.data', 15)->where('records.data.0.id', $last->id)
            ->where('metrics.statuses', fn ($statuses): bool => collect($statuses)->sum('count') === $total)
            ->missing('records.data.0.history')->missing('records.data.0.attachments')->missing('records.data.0.email')->missing('records.data.0.cpf'));
        $this->get(route('admin.reports.'.$kind, ['page' => 2]))->assertInertia(fn (Assert $page) => $page->where('metrics.total', $total)->has('records.data', $total - 15));
    }

    #[DataProvider('kinds')]
    public function test_combined_filters_and_each_dimension_use_the_same_query(string $kind): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $other = User::factory()->morador()->create();
        $area = CommonArea::factory()->create();
        $provider = ServiceProvider::factory()->create();
        $date = $kind === 'reservations' ? 'starts_at' : 'created_at';
        $attributes = match ($kind) {
            'reservations' => ['user_id' => $resident->id, 'unit_id' => $resident->unit_id, 'common_area_id' => $area->id, 'status' => 'pending'],
            'incidents' => ['resident_id' => $resident->id, 'unit_id' => $resident->unit_id, 'status' => 'open', 'type' => 'maintenance_request', 'category' => 'Legado local', 'priority' => 'high'],
            'maintenances' => ['service_provider_id' => $provider->id, 'status' => 'pending', 'incident_id' => null],
        };
        $filters = $attributes;
        if ($kind === 'reservations') {
            unset($filters['user_id']);
            $filters['resident_id'] = $resident->id;
        }
        if ($kind === 'maintenances') {
            unset($filters['incident_id']);
            $filters['origin'] = 'direct';
        }
        $attributes[$date] = '2026-10-01 23:59:59';
        $filters += ['date_from' => '2026-10-01', 'date_to' => '2026-10-01'];
        for ($i = 0; $i < 17; $i++) {
            $last = $this->record($kind, $attributes);
        }
        $differences = match ($kind) {
            'reservations' => ['user_id' => $other->id, 'unit_id' => $other->unit_id, 'common_area_id' => CommonArea::factory()->create()->id, 'status' => 'cancelled'],
            'incidents' => ['resident_id' => $other->id, 'unit_id' => $other->unit_id, 'status' => 'canceled', 'type' => 'incident', 'category' => 'noise', 'priority' => 'low'],
            'maintenances' => ['service_provider_id' => ServiceProvider::factory()->create()->id, 'status' => 'canceled', 'incident_id' => Incident::factory()->create()->id],
        };
        $differences[$date] = '2026-10-02 00:00:00';
        foreach ($differences as $field => $value) {
            $this->record($kind, array_replace($attributes, [$field => $value]));
        }
        $this->actingAs($admin)->get(route('admin.reports.'.$kind, $filters))->assertInertia(fn (Assert $page) => $page
            ->where('records.total', 17)->where('metrics.total', 17)->where('records.data.0.id', $last->id));
        foreach (array_diff_key($filters, ['date_from' => true, 'date_to' => true]) as $field => $value) {
            $this->get(route('admin.reports.'.$kind, [$field => $value]))->assertInertia(fn (Assert $page) => $page->where('records.total', 16 + count($differences)));
        }
        $result = app(ReportQueryService::class)->report($admin, $kind, $filters);
        $this->assertStringContainsString('date_to=2026-10-01', $result['records']->url(2));
        $this->assertStringContainsString('status=', $result['records']->url(2));
        $this->get(route('admin.reports.'.$kind, $filters + ['page' => 2]))->assertInertia(fn (Assert $page) => $page->has('records.data', 2)->where('metrics.total', 17));
        $ids = $this->csvIds($this->get(route('admin.reports.'.$kind.'.export', $filters + ['page' => 2]))->streamedContent());
        $this->assertCount(17, $ids);
        $this->assertSame($last->id, $ids[0]);
        $this->assertSame(array_column($result['records']->items(), 'id'), array_slice($ids, 0, 15));
    }

    #[DataProvider('periods')]
    public function test_dates_are_inclusive_and_only_selected_reference_is_used(string $kind, ?string $reference): void
    {
        $column = match ($reference) {
            'scheduled' => 'scheduled_at', 'executed' => 'executed_at', default => $kind === 'reservations' ? 'starts_at' : 'created_at'
        };
        foreach (['2026-09-30 23:59:59', '2026-10-01 00:00:00', '2026-10-01 23:59:59', '2026-10-02 00:00:00'] as $date) {
            $this->record($kind, [$column => $date]);
        }
        if (in_array($column, ['scheduled_at', 'executed_at'])) {
            $this->record($kind, [$column => null]);
        }
        $this->actingAs(User::factory()->admin()->create());
        foreach ([[[], in_array($column, ['scheduled_at', 'executed_at']) ? 5 : 4], [['date_from' => '2026-10-01'], 3], [['date_to' => '2026-10-01'], 3], [['date_from' => '2026-10-01', 'date_to' => '2026-10-01'], 2]] as [$filters, $count]) {
            $this->get(route('admin.reports.'.$kind, $filters + ['date_reference' => $reference]))->assertInertia(fn (Assert $page) => $page->where('records.total', $count)->where('metrics.total', $count));
            $this->assertCount($count, $this->csvIds($this->get(route('admin.reports.'.$kind.'.export', $filters + ['date_reference' => $reference]))->streamedContent()));
        }
    }

    public function test_costs_exclude_null_include_zero_and_preserve_archived_provider_and_linked_incident(): void
    {
        $provider = ServiceProvider::factory()->create();
        $provider->delete();
        $incident = Incident::factory()->create();
        $incident->delete();
        foreach (['100.00', '200.00', null, '0.00'] as $cost) {
            $this->record('maintenances', ['cost' => $cost, 'service_provider_id' => $provider->id, 'incident_id' => $incident->id]);
        }
        $this->record('maintenances', ['cost' => '99999999.99']);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.maintenances', ['service_provider_id' => $provider->id]))->assertInertia(fn (Assert $page) => $page
            ->where('metrics.cost_total', '300.00')->where('metrics.cost_average', '100.00')->where('metrics.cost_count', 3)
            ->where('records.total', 4)->where('records.data.0.provider', $provider->name)->where('records.data.0.origin', 'Vinculada')
            ->where('records.data.0.incident', '#'.$incident->id.' — '.$incident->title));
        $this->get(route('admin.reports.maintenances', ['origin' => 'direct']))->assertInertia(fn (Assert $page) => $page
            ->where('metrics.cost_total', '99999999.99')->where('metrics.cost_average', '99999999.99')->where('records.data.0.incident', null)->missing('records.data.0.resident'));
    }

    public function test_cost_average_rounds_half_up_without_binary_float_accumulation(): void
    {
        foreach (['0.10', '0.20', '0.00', '0.00'] as $cost) {
            $this->record('maintenances', ['cost' => $cost]);
        }
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.maintenances'))->assertInertia(fn (Assert $page) => $page->where('metrics.cost_total', '0.30')->where('metrics.cost_average', '0.08'));
    }

    public function test_null_only_costs_and_totals_larger_than_a_single_record_limit(): void
    {
        $this->record('maintenances');
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.maintenances'))->assertInertia(fn (Assert $page) => $page
            ->where('metrics.total', 1)->where('metrics.cost_total', '0.00')->where('metrics.cost_average', null)->where('metrics.cost_count', 0));
        $this->record('maintenances', ['cost' => '99999999.99']);
        $this->record('maintenances', ['cost' => '99999999.99']);
        $this->get(route('admin.reports.maintenances'))->assertInertia(fn (Assert $page) => $page->where('metrics.cost_total', '199999999.98')->where('metrics.cost_average', '99999999.99'));
    }

    #[DataProvider('kinds')]
    public function test_empty_results_have_zero_counts_and_unavailable_average(string $kind): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.'.$kind))->assertInertia(function (Assert $page) use ($kind): void {
            $page->where('metrics.total', 0)->has('records.data', 0)->where('metrics.statuses', fn ($statuses): bool => collect($statuses)->sum('count') === 0);
            if ($kind === 'maintenances') {
                $page->where('metrics.cost_total', '0.00')->where('metrics.cost_average', null);
            }
        });
    }

    #[DataProvider('accessCases')]
    public function test_page_and_export_share_authorization(string $kind, bool $csv, string $role): void
    {
        $url = route('admin.reports.'.$kind.($csv ? '.export' : ''));
        if ($role === 'guest') {
            $this->get($url)->assertRedirect(route('login'));

            return;
        }
        $user = User::factory()->create(['role' => in_array($role, ['inactive', 'unverified']) ? 'admin' : $role,
            'is_active' => $role !== 'inactive', 'email_verified_at' => $role === 'unverified' ? null : now()]);
        $response = $this->actingAs($user)->get($url);
        match ($role) {
            'admin' => $response->assertOk(), 'inactive' => $response->assertRedirect(route('login')),
            'unverified' => $response->assertRedirect(route('verification.notice')), default => $response->assertForbidden(),
        };
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_controlled_for_page_and_csv(string $kind, array $filters, string $field): void
    {
        $this->actingAs(User::factory()->admin()->create());
        foreach (['', '.export'] as $suffix) {
            $this->getJson(route('admin.reports.'.$kind.$suffix, $filters))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    #[DataProvider('kinds')]
    public function test_query_count_does_not_grow_per_row_and_does_not_load_histories(string $kind): void
    {
        $admin = User::factory()->admin()->create();
        $this->record($kind);
        $service = app(ReportQueryService::class);
        DB::enableQueryLog();
        $service->report($admin, $kind, []);
        $one = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 0; $i < 14; $i++) {
            $this->record($kind);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $service->report($admin, $kind, []);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame($one, count($queries));
        $sql = implode(' ', array_column($queries, 'query'));
        $this->assertStringNotContainsString('histories', $sql);
        $this->assertStringNotContainsString('incident_attachments', $sql);
        $this->assertStringNotContainsString('maintenance_request_changes', $sql);
    }

    /** @return list<int> */
    private function csvIds(string $csv): array
    {
        $stream = fopen('php://memory', 'w+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        fgetcsv($stream, null, ';', '"', '');
        $ids = [];
        while (($row = fgetcsv($stream, null, ';', '"', '')) !== false) {
            $ids[] = (int) $row[0];
        }
        fclose($stream);

        return $ids;
    }

    private function record(string $kind, array $attributes = []): Model
    {
        return match ($kind) {
            'reservations' => Reservation::factory()->create($attributes + ['starts_at' => '2026-10-01 12:00:00', 'ends_at' => '2026-10-03 14:00:00', 'status' => 'pending']),
            'incidents' => Incident::factory()->create($attributes + ['created_at' => '2026-10-01 12:00:00', 'opened_at' => '2026-01-01']),
            'maintenances' => MaintenanceRequest::factory()->administrativeDirect()->create($attributes + ['created_at' => '2026-10-01 12:00:00']),
        };
    }

    public static function kinds(): array
    {
        return [['reservations'], ['incidents'], ['maintenances']];
    }

    public static function periods(): array
    {
        return [['reservations', null], ['incidents', null], ['maintenances', 'opening'], ['maintenances', 'scheduled'], ['maintenances', 'executed']];
    }

    public static function accessCases(): array
    {
        $cases = [];
        foreach (self::kinds() as [$kind]) {
            foreach ([false, true] as $csv) {
                foreach (['admin', 'morador', 'porteiro', 'guest', 'inactive', 'unverified'] as $role) {
                    $cases[] = [$kind, $csv, $role];
                }
            }
        }

        return $cases;
    }

    public static function invalidFilters(): array
    {
        $cases = [];
        foreach (self::kinds() as [$kind]) {
            foreach ([['date_from' => 'invalid'], ['date_to' => '2026-02-30'], ['date_to' => '2026-10-01', 'date_from' => '2026-10-02'], ['status' => 'invalid'], ['sort' => 'id desc; DROP TABLE users'], ['direction' => 'asc'], ['order_by' => 'cost'], ['page' => 0]] as $filters) {
                $cases[] = [$kind, $filters, array_key_first($filters)];
            }
        }
        foreach (['reservations' => ['common_area_id', 'unit_id', 'resident_id'], 'incidents' => ['unit_id', 'resident_id'], 'maintenances' => ['service_provider_id']] as $kind => $fields) {
            foreach ($fields as $field) {
                $cases[] = [$kind, [$field => 999999], $field];
            }
        }
        foreach (['incidents' => ['type', 'category', 'priority'], 'maintenances' => ['date_reference', 'origin']] as $kind => $fields) {
            foreach ($fields as $field) {
                $cases[] = [$kind, [$field => 'invalid'], $field];
            }
        }

        return $cases;
    }
}
