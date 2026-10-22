<?php

namespace Tests\Feature;

use App\Enums\IncidentCategory;
use App\Enums\IncidentStatus;
use App\Enums\ReservationStatus;
use App\Enums\VisitorAccessStatus;
use App\Models\CommonArea;
use App\Models\Incident;
use App\Models\Reservation;
use App\Models\Unit;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-10-22 12:00:00'));
    }

    public function test_admin_views_three_reports_with_totals_and_sanitized_details(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = Unit::factory()->create(['block' => 'A', 'number' => '101', 'complement' => null]);
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id, 'name' => 'Maria Moradora']);
        $area = CommonArea::factory()->create(['name' => 'Salão principal']);
        Reservation::factory()->create([
            'common_area_id' => $area->id, 'unit_id' => $unit->id, 'user_id' => $resident->id,
            'starts_at' => '2026-10-23 14:00:00', 'ends_at' => '2026-10-23 16:00:00', 'status' => ReservationStatus::Approved,
        ]);
        $entryDoorman = User::factory()->porteiro()->create(['name' => 'Paulo Entrada']);
        $exitDoorman = User::factory()->porteiro()->create(['name' => 'Rita Saída']);
        $visitor = Visitor::factory()->create(['unit_id' => $unit->id, 'name' => 'Helena Visitante', 'cpf' => '529.982.247-25', 'phone' => '65999999999']);
        $authorization = VisitorAuthorization::factory()->active()->create([
            'visitor_id' => $visitor->id, 'unit_id' => $unit->id, 'resident_id' => $resident->id,
            'access_code' => 'codigo-secreto', 'invitation_token_hash' => hash('sha256', 'token-secreto'),
        ]);
        VisitorAccess::factory()->create([
            'visitor_authorization_id' => $authorization->id,
            'doorman_id' => $entryDoorman->id, 'exit_doorman_id' => $exitDoorman->id,
            'entry_time' => '2026-10-22 09:00:00', 'exit_time' => '2026-10-22 10:00:00',
            'validation_status' => VisitorAccessStatus::Validated, 'observations' => 'Observação privada',
        ]);
        Incident::factory()->create([
            'unit_id' => $unit->id, 'resident_id' => $resident->id, 'title' => 'Portão com falha',
            'category' => IncidentCategory::Security, 'status' => IncidentStatus::InProgress,
            'opened_at' => '2026-10-21 08:00:00', 'updated_at' => '2026-10-22 11:00:00',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports')
            ->where('reservations.total', 1)
            ->where('reservations.data.0.area', 'Salão principal')
            ->where('reservations.data.0.resident', 'Maria Moradora')
            ->where('reservations.data.0.unit', 'A · 101')
            ->where('reservations.data.0.status_label', 'Aprovada')
            ->where('accesses.total', 1)
            ->where('accesses.data.0.visitor', 'Helena Visitante')
            ->where('accesses.data.0.entry_doorman', 'Paulo Entrada')
            ->where('accesses.data.0.exit_doorman', 'Rita Saída')
            ->where('accesses.data.0.situation', 'finished')
            ->where('incidents.total', 1)
            ->where('incidents.data.0.category_label', 'Segurança')
            ->where('incidents.data.0.title', 'Portão com falha')
            ->where('incidents.data.0.status_label', 'Em andamento')
            ->has('options.common_areas', 1)
            ->has('options.reservation_statuses', 4)
            ->has('options.access_situations', 5)
            ->has('options.incident_categories', 5)
            ->has('options.incident_statuses', 4));
        $content = $response->getContent();
        $this->assertStringNotContainsString($visitor->cpf, $content);
        $this->assertStringNotContainsString($visitor->phone, $content);
        $this->assertStringNotContainsString('codigo-secreto', $content);
        $this->assertStringNotContainsString('Observação privada', $content);
    }

    public function test_each_report_combines_filters_and_includes_the_entire_final_day(): void
    {
        $admin = User::factory()->admin()->create();
        $area = CommonArea::factory()->create();
        Reservation::factory()->create(['common_area_id' => $area->id, 'starts_at' => '2026-10-20 23:59:59', 'ends_at' => '2026-10-21 01:00:00', 'status' => ReservationStatus::Pending]);
        Reservation::factory()->create(['common_area_id' => $area->id, 'starts_at' => '2026-10-21 00:00:00', 'ends_at' => '2026-10-21 01:00:00', 'status' => ReservationStatus::Approved]);
        VisitorAccess::factory()->closed()->create(['entry_time' => '2026-10-20 23:59:59', 'exit_time' => '2026-10-21 00:30:00']);
        VisitorAccess::factory()->open()->create(['entry_time' => '2026-10-20 12:00:00']);
        Incident::factory()->create(['title' => 'Correspondente', 'category' => IncidentCategory::Noise, 'status' => IncidentStatus::Completed, 'opened_at' => '2026-10-20 23:59:59']);
        Incident::factory()->maintenanceRequest()->create(['category' => IncidentCategory::Noise, 'status' => IncidentStatus::Completed, 'opened_at' => '2026-10-20 12:00:00']);
        Incident::factory()->create(['category' => IncidentCategory::Security, 'status' => IncidentStatus::Completed, 'opened_at' => '2026-10-20 12:00:00']);

        $this->actingAs($admin)->get(route('admin.reports.index', [
            'reservation_date_from' => '2026-10-20', 'reservation_date_to' => '2026-10-20',
            'reservation_status' => 'pending', 'common_area_id' => $area->id,
            'access_date_from' => '2026-10-20', 'access_date_to' => '2026-10-20', 'access_situation' => 'finished',
            'incident_date_from' => '2026-10-20', 'incident_date_to' => '2026-10-20',
            'incident_category' => 'noise', 'incident_status' => 'completed',
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('reservations.total', 1)->where('reservations.data.0.status', 'pending')
            ->where('accesses.total', 1)->where('accesses.data.0.situation', 'finished')
            ->where('incidents.total', 1)->where('incidents.data.0.title', 'Correspondente')
            ->where('filters.reservation_date_to', '2026-10-20')
            ->where('filters.access_situation', 'finished')
            ->where('filters.incident_category', 'noise'));
    }

    public function test_section_pagination_is_independent(): void
    {
        $admin = User::factory()->admin()->create();
        Reservation::factory()->count(11)->create();
        VisitorAccess::factory()->open()->create();
        Incident::factory()->create();

        $this->actingAs($admin)->get(route('admin.reports.index', ['reservations_page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('reservations.current_page', 2)->has('reservations.data', 1)
                ->where('accesses.current_page', 1)->has('accesses.data', 1)
                ->where('incidents.current_page', 1)->has('incidents.data', 1));
    }

    public function test_report_route_is_admin_only_without_changing_portaria_permissions(): void
    {
        $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->morador()->create())->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs(User::factory()->porteiro()->create())->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->unverified()->create())->get(route('admin.reports.index'))->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->admin()->inactive()->create())->get(route('admin.reports.index'))->assertRedirect(route('login'));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('portaria.visitor-access-history.index'))->assertForbidden();
        $this->get(route('admin.reports.index'))->assertOk();
    }

    public function test_invalid_report_filters_are_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())->getJson(route('admin.reports.index', [
            'reservation_date_from' => '2026-10-22', 'reservation_date_to' => '2026-10-21',
            'reservation_status' => 'invalid', 'common_area_id' => 999999,
            'access_situation' => 'invalid', 'incident_category' => 'invalid',
            'incident_status' => 'invalid', 'incidents_page' => 0,
        ]))->assertUnprocessable()->assertJsonValidationErrors([
            'reservation_date_to', 'reservation_status', 'common_area_id', 'access_situation',
            'incident_category', 'incident_status', 'incidents_page',
        ]);
    }
}
