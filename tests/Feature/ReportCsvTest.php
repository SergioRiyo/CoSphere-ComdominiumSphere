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
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportCsvTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_incident_report_filters_orders_and_exports_by_opening_date(): void
    {
        $first = Incident::factory()->create(['opened_at' => '2026-10-01 12:00:00', 'created_at' => '2026-10-03 12:00:00']);
        $last = Incident::factory()->create(['opened_at' => '2026-10-01 14:00:00', 'created_at' => '2026-09-01 12:00:00']);
        Incident::factory()->create(['opened_at' => '2026-10-02 12:00:00', 'created_at' => '2026-10-01 12:00:00']);
        $filters = ['date_from' => '2026-10-01', 'date_to' => '2026-10-01'];

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.incidents', $filters))
            ->assertInertia(fn (Assert $page) => $page->where('records.total', 2)
                ->where('records.data.0.id', $last->id)->where('records.data.0.opened_at', '01/10/2026 14:00:00')
                ->where('records.data.1.id', $first->id));
        $rows = $this->parse($this->get(route('admin.reports.incidents.export', $filters))->streamedContent());
        $this->assertSame([(string) $last->id, (string) $first->id], array_column(array_slice($rows, 1), 0));
        $this->assertSame('01/10/2026 14:00:00', $rows[1][8]);
    }

    public function test_common_area_maintenance_is_identified_on_report_and_csv(): void
    {
        $area = CommonArea::factory()->create(['name' => '=Salao']);
        $maintenance = MaintenanceRequest::factory()->forCommonArea($area)->create();

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.maintenances'))
            ->assertInertia(fn (Assert $page) => $page->where('records.total', 1)
                ->where('records.data.0.id', $maintenance->id)->where('records.data.0.area', '=Salao')
                ->where('records.data.0.unit', null));
        $rows = $this->parse($this->get(route('admin.reports.maintenances.export'))->streamedContent());
        $this->assertSame('Área comum', $rows[0][12]);
        $this->assertSame("'=Salao", $rows[1][12]);
        $this->assertSame('', $rows[1][2]);
    }

    #[DataProvider('kinds')]
    public function test_multiple_batches_match_table_order_totals_and_filters_without_duplicates(string $kind): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $count = ReportQueryService::EXPORT_BATCH_SIZE * 2 + 13;
        $factory = match ($kind) {
            'reservations' => Reservation::factory()->state(['user_id' => $resident->id, 'unit_id' => $resident->unit_id, 'common_area_id' => CommonArea::factory()->create()->id, 'status' => 'pending', 'ends_at' => '2026-10-03 14:00:00']),
            'incidents' => Incident::factory()->state(['resident_id' => $resident->id, 'unit_id' => $resident->unit_id, 'status' => 'open', 'category' => 'Categoria antiga']),
            'maintenances' => MaintenanceRequest::factory()->administrativeDirect()->state(['unit_id' => $resident->unit_id, 'admin_id' => $admin->id, 'cost' => '0.10']),
        };
        $column = match ($kind) {
            'reservations' => 'starts_at', 'incidents' => 'opened_at', 'maintenances' => 'scheduled_at'
        };
        $records = $factory->count($count)->sequence(fn (Sequence $sequence): array => [$column => $kind === 'maintenances' && $sequence->index >= 240 ? null : ($sequence->index % 2 === 0 ? '2026-10-01 12:00:00' : '2026-10-02 12:00:00')])->create();
        $factory->create(['status' => $kind === 'reservations' ? 'cancelled' : 'canceled']);
        $filters = ['status' => $kind === 'incidents' ? 'open' : 'pending'];
        if ($kind === 'maintenances') {
            $filters += ['date_reference' => 'scheduled'];
        }
        $expected = $records->sort(function ($a, $b) use ($column): int {
            $aDate = $a->getRawOriginal($column);
            $bDate = $b->getRawOriginal($column);

            return ($aDate === null ? 1 : 0) <=> ($bDate === null ? 1 : 0) ?: strcmp($bDate ?? '', $aDate ?? '') ?: $b->id <=> $a->id;
        })->pluck('id')->all();
        $report = app(ReportQueryService::class)->report($admin, $kind, $filters);
        $this->assertSame($count, $report['records']->total());
        $this->assertSame($count, $report['metrics']['total']);
        $this->assertSame($count, array_sum(array_column($report['metrics']['statuses'], 'count')));
        $this->assertSame(array_slice($expected, 0, 15), array_column($report['records']->items(), 'id'));
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($admin)->get(route('admin.reports.'.$kind.'.export', $filters + ['page' => 2]));
        $response->assertOk()->assertStreamed()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $rows = $this->parse($csv);
        $this->assertSame($expected, array_map('intval', array_column(array_slice($rows, 1), 0)));
        $this->assertCount($count + 1, $rows);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.csv', $response->headers->get('Content-Disposition'));
        $table = match ($kind) {
            'reservations' => 'reservations', 'incidents' => 'incidents', 'maintenances' => 'maintenance_requests'
        };
        $batches = array_values(array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'from "'.$table.'"') && str_contains($query['query'], 'order by')));
        $this->assertCount(3, $batches);
        foreach ($batches as $batch) {
            $this->assertStringContainsString('limit '.ReportQueryService::EXPORT_BATCH_SIZE, $batch['query']);
            $this->assertStringNotContainsString('offset', $batch['query']);
        }
    }

    public function test_csv_preserves_accents_quotes_newlines_and_blanks_without_sensitive_data(): void
    {
        $provider = ServiceProvider::factory()->create(['name' => "José; \"Elétrico\"\nEquipe", 'cpf_cnpj' => '12345678901', 'phone' => 'SECRET PHONE', 'email' => 'secret@example.com']);
        $provider->delete();
        $maintenance = MaintenanceRequest::factory()->administrativeDirect()->create(['description' => "Revisão; \"porta\"\r\nSegunda linha", 'service_provider_id' => $provider->id, 'cost' => null]);
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.maintenances.export'));
        $csv = $response->streamedContent();
        $rows = $this->parse($csv);
        $this->assertSame(['ID', 'Descrição', 'Unidade', 'Origem', 'Ocorrência vinculada', 'Administrador responsável', 'Prestador', 'Status', 'Data de abertura', 'Agendamento', 'Execução', 'Custo', 'Área comum'], $rows[0]);
        $this->assertSame($maintenance->description, $rows[1][1]);
        $this->assertSame($provider->name, $rows[1][6]);
        $this->assertSame('Direta', $rows[1][3]);
        foreach ([4, 9, 10, 11] as $index) {
            $this->assertSame('', $rows[1][$index]);
        }
        foreach (['12345678901', 'SECRET PHONE', 'secret@example.com'] as $secret) {
            $this->assertStringNotContainsString($secret, $csv);
        }
        $this->assertStringContainsString('"Revisão; ""porta""', $csv);
    }

    #[DataProvider('dangerousCells')]
    public function test_csv_formula_protection_does_not_modify_database(string $value): void
    {
        $incident = Incident::factory()->create(['title' => $value]);
        $csv = $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.incidents.export'))->streamedContent();
        $this->assertSame("'".$value, $this->parse($csv)[1][1]);
        $this->assertSame($value, $incident->fresh()->title);
    }

    public function test_formula_protection_also_applies_to_names_and_maintenance_descriptions(): void
    {
        $resident = User::factory()->morador()->create(['name' => '=1+1']);
        $area = CommonArea::factory()->create(['name' => '@SUM(1;1)']);
        Reservation::factory()->create(['user_id' => $resident->id, 'unit_id' => $resident->unit_id, 'common_area_id' => $area->id]);
        $this->actingAs(User::factory()->admin()->create());
        $row = $this->parse($this->get(route('admin.reports.reservations.export'))->streamedContent())[1];
        $this->assertSame("'@SUM(1;1)", $row[1]);
        $this->assertSame("'=1+1", $row[6]);
        $provider = ServiceProvider::factory()->create(['name' => '-1+2']);
        MaintenanceRequest::factory()->administrativeDirect()->create(['description' => '+1+1', 'service_provider_id' => $provider->id, 'cost' => '0.00']);
        $row = $this->parse($this->get(route('admin.reports.maintenances.export'))->streamedContent())[1];
        $this->assertSame("'+1+1", $row[1]);
        $this->assertSame("'-1+2", $row[6]);
        $this->assertSame('0,00', $row[11]);
    }

    #[DataProvider('kinds')]
    public function test_empty_csv_contains_headers_only(string $kind): void
    {
        $csv = $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.'.$kind.'.export'))->assertOk()->streamedContent();
        $rows = $this->parse($csv);
        $this->assertCount(1, $rows);
        $this->assertSame('ID', $rows[0][0]);
        $this->assertCount(match ($kind) {
            'reservations' => 8, 'incidents' => 9, 'maintenances' => 13
        }, $rows[0]);
    }

    #[DataProvider('kinds')]
    public function test_csv_includes_translated_terminal_states(string $kind): void
    {
        $cases = match ($kind) {
            'reservations' => ReservationStatus::cases(), 'incidents' => IncidentStatus::cases(), 'maintenances' => MaintenanceRequestStatus::cases()
        };
        foreach ($cases as $case) {
            match ($kind) {
                'reservations' => Reservation::factory()->create(['status' => $case]),
                'incidents' => Incident::factory()->create(['status' => $case, 'category' => 'Histórica']),
                'maintenances' => MaintenanceRequest::factory()->administrativeDirect()->create(['status' => $case]),
            };
        }
        $csv = $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.'.$kind.'.export'))->streamedContent();
        $rows = $this->parse($csv);
        $this->assertCount(count($cases) + 1, $rows);
        $this->assertEqualsCanonicalizing(array_map(fn ($case): string => $case->label(), $cases), array_column(array_slice($rows, 1), $kind === 'maintenances' ? 7 : 5));
        if ($kind === 'incidents') {
            $this->assertSame('Histórica', $rows[1][3]);
        }
    }

    /** @return list<list<string|null>> */
    private function parse(string $csv): array
    {
        $stream = fopen('php://memory', 'w+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, ';', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    public static function kinds(): array
    {
        return [['reservations'], ['incidents'], ['maintenances']];
    }

    public static function dangerousCells(): array
    {
        return [['=SUM(1;2)'], ['+1+1'], ['-1+1'], ['@SUM(1;2)'], ["\t=1+1"], ["\r=1+1"], ["\n=1+1"], ['  =1+1'], ["\u{00A0}=1+1"], ["\u{FEFF}=1+1"]];
    }
}
