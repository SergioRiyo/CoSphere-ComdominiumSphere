<?php

namespace Tests\Feature;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\MaintenanceRequestStatus;
use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestStatusHistory;
use App\Models\User;
use App\Services\IncidentAttachmentService;
use App\Services\IncidentService;
use App\Services\MaintenanceRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class IncidentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake(IncidentAttachmentService::DISK);
    }

    #[DataProvider('types')]
    public function test_resident_registers_with_private_attachments_and_server_ownership(string $type): void
    {
        $resident = User::factory()->morador()->create();
        $other = User::factory()->morador()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($resident)->get(route('morador.incidents.create'))->assertOk();
        $this->post(route('morador.incidents.store'), $this->data() + [
            'type' => $type, 'resident_id' => $other->id, 'user_id' => $other->id, 'unit_id' => $other->unit_id,
            'priority' => 'high', 'status' => 'completed', 'created_by' => $admin->id, 'author_id' => $admin->id,
            'attachments' => [UploadedFile::fake()->image('foto.png')],
        ])->assertRedirect(route('morador.incidents.show', Incident::sole()));
        $incident = Incident::sole();
        $this->assertSame($resident->id, $incident->resident_id);
        $this->assertSame($resident->unit_id, $incident->unit_id);
        $this->assertSame(IncidentType::from($type), $incident->type);
        $this->assertSame(IncidentStatus::Open, $incident->status);
        $this->assertSame(IncidentPriority::Medium, $incident->priority);
        $this->assertSame(1, $incident->statusHistory()->count());
        $attachment = $incident->attachments()->sole();
        $this->assertSame($resident->id, $attachment->uploaded_by_user_id);
        Storage::disk(IncidentAttachmentService::DISK)->assertExists($attachment->path);
        $this->get(route('morador.incidents.show', $incident))->assertInertia(fn (Assert $page) => $page
            ->component('morador/incident-details')->where('incident.title', 'Portão avariado')
            ->has('incident.attachments', 1)->missing('incident.attachments.0.path')
            ->has('incident.history', 1)->where('incident.history.0.actor', 'Você'));
        $this->get(route('incident-attachments.download', $attachment))->assertOk();
        $this->actingAs($admin)->get(route('incident-attachments.download', $attachment))->assertOk();
    }

    public static function types(): array
    {
        return [['incident'], ['maintenance_request']];
    }

    #[DataProvider('invalidFiles')]
    public function test_http_rejects_invalid_files_and_rolls_back_previous_upload(string $scenario): void
    {
        $file = match ($scenario) {
            'extension' => UploadedFile::fake()->image('foto.exe'),
            'content' => UploadedFile::fake()->createWithContent('foto.png', '<?php echo "disfarce";'),
            'mismatch' => UploadedFile::fake()->createWithContent('foto.png', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"),
            'oversize' => UploadedFile::fake()->image('foto.png')->size(10241),
        };
        $resident = User::factory()->morador()->create();
        $this->actingAs($resident)->postJson(route('morador.incidents.store'), $this->data() + [
            'type' => 'incident', 'attachments' => [UploadedFile::fake()->image('valida.png'), $file],
        ])->assertUnprocessable()->assertJsonValidationErrors('attachments.1');
        $this->assertDatabaseCount('incidents', 0);
        $this->assertDatabaseCount('incident_status_histories', 0);
        $this->assertDatabaseCount('incident_attachments', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertSame([], Storage::disk(IncidentAttachmentService::DISK)->allFiles());
    }

    public static function invalidFiles(): array
    {
        return [['extension'], ['content'], ['mismatch'], ['oversize']];
    }

    public function test_required_fields_and_classification_are_validated(): void
    {
        $this->actingAs(User::factory()->morador()->create())->postJson(route('morador.incidents.store'), [])
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'description', 'type', 'category']);
        $this->postJson(route('morador.incidents.store'), [
            'title' => str_repeat('x', 256), 'description' => str_repeat('x', 10001),
            'type' => 'other', 'category' => 'legacy-unknown',
        ])->assertUnprocessable()->assertJsonValidationErrors(['title', 'description', 'type', 'category']);
        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_cross_user_same_unit_and_other_unit_cannot_view_download_or_operate(): void
    {
        $incident = Incident::factory()->create();
        $attachment = app(IncidentAttachmentService::class)->store($incident->resident, $incident, UploadedFile::fake()->image('foto.png'));
        foreach ([User::factory()->morador()->create(['unit_id' => $incident->unit_id]), User::factory()->morador()->create()] as $other) {
            $this->actingAs($other)->get(route('morador.incidents.show', $incident))->assertNotFound();
            $this->get(route('incident-attachments.download', $attachment))->assertForbidden();
            $this->patchJson(route('admin.incidents.status', $incident), ['status' => 'in_progress'])->assertForbidden();
            $this->patchJson(route('admin.incidents.priority', $incident), ['priority' => 'high'])->assertForbidden();
            $this->postJson(route('admin.incidents.maintenance', $incident))->assertForbidden();
        }
        $this->assertSame(IncidentStatus::Open, $incident->refresh()->status);
    }

    public function test_doorman_is_blocked_on_all_critical_routes(): void
    {
        $incident = Incident::factory()->create();
        $attachment = app(IncidentAttachmentService::class)->store($incident->resident, $incident, UploadedFile::fake()->image('foto.png'));
        $this->actingAs(User::factory()->porteiro()->create());
        foreach (['morador.incidents.index', 'admin.incidents.index', 'morador.incidents.create'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
        $this->get(route('morador.incidents.show', $incident))->assertForbidden();
        $this->get(route('admin.incidents.show', $incident))->assertForbidden();
        $this->get(route('incident-attachments.download', $attachment))->assertForbidden();
        $this->postJson(route('morador.incidents.store'), $this->data() + ['type' => 'incident'])->assertForbidden();
        $this->patchJson(route('admin.incidents.status', $incident), ['status' => 'in_progress'])->assertForbidden();
        $this->patchJson(route('admin.incidents.priority', $incident), ['priority' => 'high'])->assertForbidden();
        $this->postJson(route('admin.incidents.maintenance', $incident))->assertForbidden();
    }

    public function test_guest_inactive_unverified_and_missing_unit_are_controlled(): void
    {
        $this->get(route('morador.incidents.index'))->assertRedirect(route('login'));
        $this->post(route('morador.incidents.store'), $this->data())->assertRedirect(route('login'));
        $this->actingAs(User::factory()->morador()->inactive()->create())->get(route('morador.incidents.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->morador()->unverified()->create())->post(route('morador.incidents.store'), $this->data())->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->morador()->create(['unit_id' => null]))->postJson(route('morador.incidents.store'), $this->data() + ['type' => 'incident'])->assertForbidden();
        $this->get(route('morador.incidents.index'))->assertInertia(fn (Assert $page) => $page->where('can_create', false));
    }

    public function test_admin_updates_only_valid_statuses_and_priority_and_details_show_real_history(): void
    {
        $this->freezeTime();
        $resident = User::factory()->morador()->create();
        $incident = app(IncidentService::class)->create($resident, $this->data());
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patchJson(route('admin.incidents.status', $incident), ['status' => 'completed'])->assertUnprocessable();
        $this->patchJson(route('admin.incidents.priority', $incident), ['priority' => 'urgent'])->assertUnprocessable();
        $this->travel(1)->second();
        $this->patchJson(route('admin.incidents.status', $incident), ['status' => 'in_progress', 'reason' => 'Em análise'])->assertOk();
        $this->travel(1)->second();
        $this->patchJson(route('admin.incidents.priority', $incident), ['priority' => 'high'])->assertOk();
        $this->travel(1)->second();
        $this->patchJson(route('admin.incidents.status', $incident), ['status' => 'completed'])->assertOk();
        $this->patchJson(route('admin.incidents.status', $incident), ['status' => 'canceled'])->assertUnprocessable();
        $this->get(route('admin.incidents.show', $incident))->assertInertia(fn (Assert $page) => $page
            ->component('admin/incident-details')->where('incident.resident', $resident->name)
            ->where('incident.status', 'completed')->has('incident.allowed_statuses', 0)
            ->where('incident.can_create_maintenance', false)->has('incident.history', 4));
        $this->actingAs($resident)->get(route('morador.incidents.show', $incident))->assertInertia(fn (Assert $page) => $page
            ->where('incident.history.0.from_label', null)->where('incident.history.1.to_label', 'Em andamento')
            ->where('incident.history.2.kind', 'priority')->where('incident.history.2.to_label', 'Alta')
            ->where('incident.history.2.actor', 'Administrador')->where('incident.history.3.to_label', 'Finalizada')
            ->missing('incident.resident')->missing('incident.unit'));
    }

    #[DataProvider('cancelableStates')]
    public function test_admin_cancels_only_nonterminal_incidents(string $state): void
    {
        $incident = Incident::factory()->create(['status' => $state]);
        $this->actingAs(User::factory()->admin()->create())->patchJson(route('admin.incidents.status', $incident), ['status' => 'canceled'])->assertOk();
        $this->patchJson(route('admin.incidents.status', $incident), ['status' => 'in_progress'])->assertUnprocessable();
        $this->assertSame(1, $incident->statusHistory()->count());
    }

    public static function cancelableStates(): array
    {
        return [['open'], ['in_progress']];
    }

    public function test_admin_creates_pending_maintenance_from_historical_owner_and_rejects_duplicate(): void
    {
        $incident = Incident::factory()->create();
        $unit = $incident->unit_id;
        $incident->resident->update(['unit_id' => User::factory()->morador()->create()->unit_id]);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson(route('admin.incidents.maintenance', $incident), [
            'resident_id' => $admin->id, 'unit_id' => $admin->unit_id, 'description' => 'Forjada',
            'status' => 'completed', 'cost' => 999, 'service_provider_id' => 999,
        ])->assertCreated();
        $maintenance = MaintenanceRequest::sole();
        $this->assertSame($incident->resident_id, $maintenance->resident_id);
        $this->assertSame($unit, $maintenance->unit_id);
        $this->assertSame($incident->description, $maintenance->description);
        $this->assertSame(MaintenanceRequestStatus::Pending, $maintenance->status);
        $this->assertNull($maintenance->service_provider_id);
        $this->assertNull($maintenance->scheduled_at);
        $this->assertNull($maintenance->cost);
        $this->assertNull($maintenance->admin_id);
        $this->assertSame($admin->id, $maintenance->statusHistory()->sole()->changed_by_user_id);
        $this->assertSame(UserRole::Admin, $maintenance->statusHistory()->sole()->actor_role);
        $this->postJson(route('admin.incidents.maintenance', $incident))->assertUnprocessable();
        $this->get(route('admin.incidents.show', $incident))->assertInertia(fn (Assert $page) => $page->where('incident.can_create_maintenance', false));
        $this->actingAs($incident->resident->fresh())->get(route('morador.incidents.show', $incident))->assertInertia(fn (Assert $page) => $page
            ->has('incident.maintenance_requests', 1)->where('incident.maintenance_requests.0.status', 'pending')
            ->missing('incident.maintenance_requests.0.cost')->missing('incident.maintenance_requests.0.resident_id'));
        $maintenance->delete();
        $this->actingAs($admin)->postJson(route('admin.incidents.maintenance', $incident))->assertUnprocessable();
        $this->assertSame(1, MaintenanceRequest::withTrashed()->count());
    }

    public function test_link_creation_rejects_terminal_parent_and_rolls_back_when_history_fails(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        foreach (['completed', 'canceled'] as $status) {
            $incident = Incident::factory()->create(['status' => $status]);
            $this->postJson(route('admin.incidents.maintenance', $incident))->assertForbidden();
        }
        $incident = Incident::factory()->create();
        MaintenanceRequestStatusHistory::creating(fn (): bool => false);
        try {
            app(MaintenanceRequestService::class)->createFromIncident($admin, $incident);
            $this->fail('Expected history failure.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('maintenance_requests', 0);
            $this->assertDatabaseCount('maintenance_request_status_histories', 0);
        } finally {
            MaintenanceRequestStatusHistory::flushEventListeners();
        }
    }

    private function data(): array
    {
        return ['title' => 'Portão avariado', 'category' => 'security', 'description' => 'O portão não fecha.'];
    }
}
