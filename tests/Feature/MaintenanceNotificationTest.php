<?php

namespace Tests\Feature;

use App\Enums\MaintenanceRequestStatus;
use App\Enums\NotificationType;
use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestChange;
use App\Models\Notification;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Services\MaintenanceRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class MaintenanceNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_link_creation_notifies_once_and_effective_changes_notify_exact_incident_owner(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = Incident::factory()->create();
        $neighbor = User::factory()->morador()->create(['unit_id' => $incident->unit_id]);
        $this->actingAs($admin)->postJson(route('admin.incidents.maintenance', $incident), ['recipient_id' => $neighbor->id, 'unit_id' => $neighbor->unit_id])->assertCreated();
        $maintenance = MaintenanceRequest::sole();
        $this->assertSame($admin->id, $maintenance->admin_id);
        $this->assertSame($incident->unit_id, $maintenance->unit_id);
        $this->assertDatabaseCount('notifications', 1);
        $this->postJson(route('admin.incidents.maintenance', $incident))->assertUnprocessable();
        $provider = ServiceProvider::factory()->create();
        $this->patch(route('admin.maintenances.update', $maintenance), ['service_provider_id' => $provider->id, 'scheduled_at' => now()->addDay()->toDateTimeString(), 'status' => 'scheduled'])->assertRedirect();
        $this->assertDatabaseCount('notifications', 2);
        $date = now()->addDays(2)->toDateTimeString();
        $this->patch(route('admin.maintenances.update', $maintenance), ['scheduled_at' => $date])->assertRedirect();
        $this->assertDatabaseCount('notifications', 3);
        $this->patch(route('admin.maintenances.update', $maintenance), ['scheduled_at' => $date])->assertRedirect();
        $this->patch(route('admin.maintenances.update', $maintenance), ['cost' => '999.99', 'admin_id' => User::factory()->admin()->create()->id])->assertRedirect();
        $this->assertDatabaseCount('notifications', 3);
        $this->patch(route('admin.maintenances.update', $maintenance), ['description' => 'Descrição comunicável'])->assertRedirect();
        $this->assertDatabaseCount('notifications', 4);
        $this->get(route('admin.maintenances.index'))->assertOk();
        $this->get(route('admin.maintenances.show', $maintenance))->assertOk();
        $this->actingAs($incident->resident)->get(route('morador.incidents.show', $incident))->assertOk();
        $this->assertDatabaseCount('notifications', 4);
        foreach (Notification::all() as $notification) {
            $this->assertSame($incident->resident_id, $notification->recipient_id);
            $this->assertSame(NotificationType::Occurrence, $notification->type);
            $this->assertStringContainsString($incident->title, $notification->message);
            $this->assertStringNotContainsString('999.99', $notification->message);
            $this->assertStringNotContainsString($admin->email, $notification->message);
        }
        $this->assertStringContainsString(now()->addDays(2)->format('d/m/Y'), Notification::orderBy('id')->skip(2)->first()->message);
    }

    public function test_notification_recipient_is_incident_owner_even_when_maintenance_resident_differs(): void
    {
        $incident = Incident::factory()->create();
        $other = User::factory()->morador()->create(['unit_id' => $incident->unit_id]);
        $maintenance = MaintenanceRequest::factory()->linkedToIncident($incident)->create(['resident_id' => $other->id]);
        app(MaintenanceRequestService::class)->transition(User::factory()->admin()->create(), $maintenance, MaintenanceRequestStatus::Canceled);
        $this->assertSame($incident->resident_id, Notification::sole()->recipient_id);
    }

    #[DataProvider('failures')]
    public function test_notification_or_change_history_failure_rolls_back_entire_operation(string $operation, string $model, bool $veto): void
    {
        $incident = Incident::factory()->create();
        $admin = User::factory()->admin()->create();
        $maintenance = $operation === 'create' ? null : MaintenanceRequest::factory()->linkedToIncident($incident)->create(['admin_id' => $admin->id]);
        $before = $maintenance?->refresh()->getRawOriginal();
        $class = $model === 'notification' ? Notification::class : MaintenanceRequestChange::class;
        $class::creating(function () use ($veto): bool {
            if ($veto) {
                return false;
            }throw new RuntimeException('Falha simulada');
        });
        try {
            $service = app(MaintenanceRequestService::class);
            match ($operation) {
                'create' => $service->createFromIncident($admin, $incident),
                'status' => $service->transition($admin, $maintenance, MaintenanceRequestStatus::Canceled),
                'edit' => $service->update($admin, $maintenance, ['description' => 'Alteração']),
            };
            $this->fail('A falha obrigatória deveria abortar a operação.');
        } catch (RuntimeException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        } finally {
            $class::flushEventListeners();
        }
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('maintenance_request_status_histories', 0);
        $this->assertDatabaseCount('maintenance_request_changes', 0);
        $this->assertDatabaseCount('maintenance_requests', $maintenance ? 1 : 0);
        if ($maintenance) {
            $this->assertSame($before, $maintenance->fresh()->getRawOriginal());
        }
        $this->assertSame('open', $incident->fresh()->status->value);
    }

    public static function failures(): array
    {
        return [['create', 'notification', false], ['create', 'notification', true], ['status', 'notification', false], ['status', 'notification', true], ['edit', 'notification', false], ['edit', 'notification', true], ['edit', 'change', false], ['edit', 'change', true]];
    }

    public function test_direct_maintenance_has_no_notification_even_when_legacy_resident_exists(): void
    {
        $maintenance = MaintenanceRequest::factory()->withoutIncident()->create();
        app(MaintenanceRequestService::class)->transition(User::factory()->admin()->create(), $maintenance, MaintenanceRequestStatus::Canceled);
        $this->assertDatabaseCount('notifications', 0);
    }
}
