<?php

namespace Tests\Feature;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\IncidentPriorityHistory;
use App\Models\Notification;
use App\Models\User;
use App\Services\IncidentAttachmentService;
use App\Services\IncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class IncidentNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake(IncidentAttachmentService::DISK);
    }

    public function test_creation_notifies_exactly_author_and_every_active_admin_without_internal_data(): void
    {
        $resident = User::factory()->morador()->create();
        $admins = User::factory()->admin()->count(2)->create();
        $unverified = User::factory()->admin()->unverified()->create();
        User::factory()->admin()->create(['is_active' => false]);
        $other = User::factory()->morador()->create(['unit_id' => $resident->unit_id]);
        User::factory()->porteiro()->create();
        $this->actingAs($resident)->post(route('morador.incidents.store'), [
            ...$this->data(), 'recipient_id' => $other->id, 'recipients' => [$other->id],
        ])->assertRedirect();
        $incident = Incident::sole();
        $expected = [$resident->id, ...$admins->modelKeys(), $unverified->id];
        $this->assertEqualsCanonicalizing($expected, Notification::pluck('recipient_id')->all());
        foreach (Notification::all() as $notification) {
            $this->assertSame(NotificationType::Occurrence, $notification->type);
            $this->assertFalse($notification->is_read);
            foreach ([$incident->title, $incident->type->label(), $incident->category->label(), $incident->status->label()] as $text) {
                $this->assertStringContainsString($text, $notification->message);
            }
            $this->assertStringNotContainsString($resident->email, $notification->message);
            $this->assertStringNotContainsString($incident->description, $notification->message);
        }
        $this->get(route('morador.incidents.show', $incident))->assertOk();
        $this->get(route('morador.incidents.index'))->assertOk();
        $this->actingAs($admins->first())->get(route('admin.incidents.show', $incident))->assertOk();
        $this->get(route('admin.incidents.index'))->assertOk();
        $this->assertDatabaseCount('notifications', 4);
        $this->assertDatabaseCount('incident_status_histories', 1);
    }

    public function test_changes_notify_owner_once_and_repeated_or_invalid_operations_do_not_notify(): void
    {
        $incident = Incident::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patchJson(route('admin.incidents.status', $incident), ['status' => 'in_progress'])->assertOk();
        $this->patchJson(route('admin.incidents.status', $incident), ['status' => 'in_progress'])->assertUnprocessable();
        $this->patchJson(route('admin.incidents.status', $incident), ['status' => 'open'])->assertUnprocessable();
        $this->patchJson(route('admin.incidents.priority', $incident), ['priority' => 'high'])->assertOk();
        $this->patchJson(route('admin.incidents.priority', $incident), ['priority' => 'high'])->assertOk();
        $this->patchJson(route('admin.incidents.priority', $incident), ['priority' => 'urgent'])->assertUnprocessable();
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseCount('incident_status_histories', 1);
        $this->assertDatabaseCount('incident_priority_histories', 1);
        foreach (Notification::all() as $notification) {
            $this->assertSame($incident->resident_id, $notification->recipient_id);
            $this->assertSame(NotificationType::Occurrence, $notification->type);
            $this->assertStringContainsString($incident->title, $notification->message);
        }
        $this->assertStringContainsString(IncidentStatus::InProgress->label(), Notification::oldest('id')->first()->message);
        $this->assertStringContainsString(IncidentPriority::High->label(), Notification::latest('id')->first()->message);
        $history = $incident->priorityHistory()->sole();
        $this->assertSame(IncidentPriority::Medium, $history->from_priority);
        $this->assertSame(IncidentPriority::High, $history->to_priority);
        $this->assertSame($admin->id, $history->changedBy->id);
        $this->assertSame(UserRole::Admin, $history->actor_role);
        $admin->update(['role' => UserRole::Porteiro]);
        $this->assertSame(UserRole::Admin, $history->fresh()->actor_role);
    }

    #[DataProvider('notificationFailures')]
    public function test_notification_failure_rolls_back_business_state_history_and_uploaded_files(string $operation, bool $veto): void
    {
        $resident = User::factory()->morador()->create();
        $admin = User::factory()->admin()->create();
        $incident = $operation === 'create' ? null : Incident::factory()->create(['resident_id' => $resident->id, 'unit_id' => $resident->unit_id]);
        $attempts = 0;
        Notification::creating(function () use (&$attempts, $operation, $veto): ?bool {
            $attempts++;
            if ($operation === 'create' && $attempts === 1) {
                return null;
            }
            if ($veto) {
                return false;
            }
            throw new RuntimeException('Falha simulada de notificação');
        });
        try {
            $service = app(IncidentService::class);
            match ($operation) {
                'create' => $service->create($resident, $this->data(), [UploadedFile::fake()->image('foto.png')]),
                'status' => $service->transition($admin, $incident, IncidentStatus::InProgress),
                'priority' => $service->updatePriority($admin, $incident, IncidentPriority::High),
            };
            $this->fail('A operação deveria falhar.');
        } catch (RuntimeException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        } finally {
            Notification::flushEventListeners();
        }
        $this->assertSame($operation === 'create' ? 2 : 1, $attempts);
        $this->assertDatabaseCount('incidents', $incident ? 1 : 0);
        $this->assertDatabaseCount('incident_status_histories', 0);
        $this->assertDatabaseCount('incident_priority_histories', 0);
        $this->assertDatabaseCount('incident_attachments', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertSame([], Storage::disk(IncidentAttachmentService::DISK)->allFiles());
        if ($incident) {
            $this->assertSame(IncidentStatus::Open, $incident->fresh()->status);
            $this->assertSame(IncidentPriority::Medium, $incident->fresh()->priority);
        }
    }

    public static function notificationFailures(): array
    {
        return [['create', false], ['create', true], ['status', false], ['status', true], ['priority', false], ['priority', true]];
    }

    public function test_outer_rollback_removes_all_creation_data_and_files_after_inner_transactions_succeed(): void
    {
        $resident = User::factory()->morador()->create();
        User::factory()->admin()->create();
        try {
            DB::transaction(function () use ($resident): void {
                app(IncidentService::class)->create($resident, $this->data(), [UploadedFile::fake()->image('foto.png')]);
                $this->assertDatabaseCount('notifications', 2);
                throw new RuntimeException('Rollback externo');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Rollback externo', $exception->getMessage());
        }
        foreach (['incidents', 'incident_status_histories', 'incident_attachments', 'notifications'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame([], Storage::disk(IncidentAttachmentService::DISK)->allFiles());
    }

    public function test_priority_history_failure_rolls_back_priority_and_notification(): void
    {
        $incident = Incident::factory()->create();
        $admin = User::factory()->admin()->create();
        IncidentPriorityHistory::creating(fn (): bool => false);
        try {
            app(IncidentService::class)->updatePriority($admin, $incident, IncidentPriority::High);
            $this->fail('O veto ao histórico deveria abortar a alteração.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('histórico', $exception->getMessage());
        } finally {
            IncidentPriorityHistory::flushEventListeners();
        }
        $this->assertSame(IncidentPriority::Medium, $incident->fresh()->priority);
        $this->assertDatabaseCount('incident_priority_histories', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_stale_instances_use_locked_priority_and_do_not_duplicate_events(): void
    {
        $incident = Incident::factory()->create();
        $stale = $incident->fresh();
        $admin = User::factory()->admin()->create();
        app(IncidentService::class)->updatePriority($admin, $incident, IncidentPriority::High);
        app(IncidentService::class)->updatePriority($admin, $stale, IncidentPriority::High);
        app(IncidentService::class)->updatePriority($admin, $stale, IncidentPriority::Low);
        $this->assertDatabaseCount('incident_priority_histories', 2);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(IncidentPriority::High, $incident->priorityHistory()->get()->last()->from_priority);
        $this->assertSame(IncidentPriority::Low, $incident->fresh()->priority);
    }

    private function data(): array
    {
        return ['title' => 'Portão avariado', 'description' => 'Descrição reservada ao detalhe', 'type' => 'incident', 'category' => 'maintenance'];
    }
}
