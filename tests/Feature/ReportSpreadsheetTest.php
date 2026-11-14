<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\CommonArea;
use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\Reservation;
use App\Models\User;
use App\Models\VisitorAccess;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class ReportSpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('reports')]
    public function test_spreadsheet_has_title_issue_date_filters_styled_headers_and_filtered_data(string $kind): void
    {
        $this->travelTo(Carbon::parse('2026-11-14 12:34:56'));
        $admin = User::factory()->admin()->create();
        $filters = match ($kind) {
            'accesses' => ['access_date_from' => '2026-10-01', 'access_date_to' => '2026-10-01', 'access_situation' => 'present'],
            'reservations' => ['date_from' => '2026-10-01', 'date_to' => '2026-10-01', 'status' => 'pending'],
            'incidents' => ['date_from' => '2026-10-01', 'date_to' => '2026-10-01', 'status' => 'open'],
            'maintenances' => ['date_from' => '2026-10-01', 'date_to' => '2026-10-01', 'status' => 'pending'],
        };
        $expected = match ($kind) {
            'accesses' => 'Acessos', 'reservations' => 'Reservas', 'incidents' => 'Ocorrências', 'maintenances' => 'Manutenções',
        };
        match ($kind) {
            'accesses' => VisitorAccess::factory()->open()->create(['entry_time' => '2026-10-01 09:00:00']),
            'reservations' => Reservation::factory()->create(['starts_at' => '2026-10-01 09:00:00', 'ends_at' => '2026-10-01 10:00:00', 'status' => ReservationStatus::Pending]),
            'incidents' => Incident::factory()->create(['title' => '=Teste', 'opened_at' => '2026-10-01 09:00:00', 'status' => 'open']),
            'maintenances' => MaintenanceRequest::factory()->administrativeDirect()->create(['created_at' => '2026-10-01 09:00:00', 'status' => 'pending', 'cost' => '12.50']),
        };
        $response = $this->actingAs($admin)->get(route('admin.reports.'.$kind.'.spreadsheet', $filters))
            ->assertOk()->assertStreamed()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));
        [$sheet, $styles] = $this->unpack($response->streamedContent());
        $this->assertStringContainsString('Relatório de '.$expected, $sheet);
        $this->assertStringContainsString('14/11/2026 12:34:56', $sheet);
        $this->assertStringContainsString('Data inicial: 01/10/2026', $sheet);
        $this->assertStringContainsString('Data final: 01/10/2026', $sheet);
        $this->assertStringContainsString($kind === 'accesses' ? 'Situação: Presente' : 'Status:', $sheet);
        $this->assertStringContainsString('r="A5" t="inlineStr" s="3"', $sheet);
        $this->assertStringContainsString('<row r="6">', $sheet);
        $this->assertStringNotContainsString('<row r="7">', $sheet);
        $this->assertStringContainsString('FF123768', $styles);
        $this->assertStringContainsString('autoFilter', $sheet);
        if ($kind === 'incidents') {
            $this->assertStringContainsString('=Teste', $sheet);
            $this->assertStringNotContainsString('<f>', $sheet);
        }
        if ($kind === 'maintenances') {
            $this->assertStringContainsString('<c r="L6" t="n"><v>12.50</v></c>', $sheet);
        }
    }

    public function test_spreadsheet_requires_admin_and_rejects_invalid_filters(): void
    {
        $url = route('admin.reports.accesses.spreadsheet');
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->morador()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('admin.reports.accesses.spreadsheet', ['access_situation' => 'incorrect']))
            ->assertUnprocessable()->assertJsonValidationErrors('access_situation');
    }

    public function test_spreadsheet_exports_all_filtered_records_beyond_the_displayed_page(): void
    {
        $area = CommonArea::factory()->create(['name' => 'Salão principal']);
        $resident = User::factory()->morador()->create();
        Reservation::factory()->count(16)->create([
            'common_area_id' => $area->id, 'user_id' => $resident->id, 'unit_id' => $resident->unit_id,
            'starts_at' => '2026-10-01 09:00:00', 'ends_at' => '2026-10-01 10:00:00', 'status' => ReservationStatus::Pending,
        ]);
        Reservation::factory()->create(['status' => ReservationStatus::Cancelled]);
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.reports.reservations.spreadsheet', ['status' => 'pending', 'common_area_id' => $area->id, 'page' => 2]))->assertOk();
        [$sheet] = $this->unpack($response->streamedContent());
        $this->assertStringContainsString('Área comum: Salão principal', $sheet);
        $this->assertStringContainsString('Status: Pendente', $sheet);
        $this->assertStringContainsString('<row r="21">', $sheet);
        $this->assertStringNotContainsString('<row r="22">', $sheet);
    }

    /** @return array{string, string} */
    private function unpack(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-test');
        file_put_contents($path, $content);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $styles = $zip->getFromName('xl/styles.xml');
        $zip->close();
        unlink($path);
        $this->assertIsString($sheet);
        $this->assertIsString($styles);
        $this->assertNotFalse(simplexml_load_string($sheet));

        return [$sheet, $styles];
    }

    public static function reports(): array
    {
        return [['accesses'], ['reservations'], ['incidents'], ['maintenances']];
    }
}
