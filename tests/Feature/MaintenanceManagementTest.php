<?php

namespace Tests\Feature;

use App\Enums\MaintenanceRequestStatus;
use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestChange;
use App\Models\ServiceProvider;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaintenanceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->startOfSecond());
    }

    public function test_admin_creates_direct_maintenance_without_inventing_resident_and_preserves_responsible(): void
    {
        $author = User::factory()->admin()->create();
        $responsible = User::factory()->admin()->create();
        $unit = Unit::factory()->create();
        $this->actingAs($author)->post(route('admin.maintenances.store'), ['unit_id' => $unit->id, 'admin_id' => $responsible->id, 'description' => 'Reparo direto', 'cost' => '120.50'])->assertRedirect();
        $maintenance = MaintenanceRequest::sole();
        $this->assertNull($maintenance->incident_id);
        $this->assertNull($maintenance->resident_id);
        $this->assertNull($maintenance->service_provider_id);
        $this->assertSame($unit->id, $maintenance->unit_id);
        $this->assertSame($responsible->id, $maintenance->admin_id);
        $this->assertSame('120.50', $maintenance->cost);
        $this->assertSame(MaintenanceRequestStatus::Pending, $maintenance->status);
        $this->assertSame($author->id, $maintenance->statusHistory()->sole()->changed_by_user_id);
        $provider = ServiceProvider::factory()->create();
        $this->patch(route('admin.maintenances.update', $maintenance), ['status' => 'in_progress', 'service_provider_id' => $provider->id])->assertRedirect();
        $this->assertSame($responsible->id, $maintenance->fresh()->admin_id);
        $this->assertNull($maintenance->fresh()->scheduled_at);
        $this->assertDatabaseCount('notifications', 0);
    }

    #[DataProvider('transitions')]
    public function test_real_routes_enforce_entire_transition_matrix(string $from, string $to, bool $allowed): void
    {
        $admin = User::factory()->admin()->create();
        $provider = ServiceProvider::factory()->create();
        $incident = Incident::factory()->create();
        $maintenance = MaintenanceRequest::factory()->linkedToIncident($incident)->create(['status' => $from, 'admin_id' => $admin->id, 'service_provider_id' => $provider->id, 'scheduled_at' => $from === 'pending' ? null : now(), 'executed_at' => $from === 'completed' ? now() : null]);
        $before = $maintenance->refresh()->getRawOriginal();
        $response = $this->actingAs($admin)->patchJson(route('admin.maintenances.update', $maintenance), ['status' => $to, 'scheduled_at' => now()->toDateTimeString(), 'reason' => 'Verificado']);
        if (! $allowed) {
            $response->assertUnprocessable();
            $this->assertSame($before, $maintenance->fresh()->getRawOriginal());
            $this->assertDatabaseCount('maintenance_request_status_histories', 0);
            $this->assertDatabaseCount('maintenance_request_changes', 0);
            $this->assertDatabaseCount('notifications', 0);

            return;
        }
        $response->assertRedirect();
        $maintenance->refresh();
        $this->assertSame($to, $maintenance->status->value);
        $history = $maintenance->statusHistory()->sole();
        $this->assertSame($from, $history->from_status->value);
        $this->assertSame($to, $history->to_status->value);
        $this->assertSame($admin->id, $history->changed_by_user_id);
        $this->assertSame(UserRole::Admin, $history->actor_role);
        $this->assertSame('Verificado', $history->reason);
        $this->assertSame(now()->toDateTimeString(), $history->created_at->toDateTimeString());
        $this->assertSame('open', $incident->fresh()->status->value);
        $this->assertDatabaseCount('notifications', 1);
        if ($to === 'completed') {
            $this->assertSame(now()->toDateTimeString(), $maintenance->executed_at->toDateTimeString());
        }
        $this->patchJson(route('admin.maintenances.update', $maintenance), ['status' => $to])->assertUnprocessable();
        $this->assertSame(1, $maintenance->statusHistory()->count());
        $this->assertDatabaseCount('notifications', 1);
    }

    public static function transitions(): array
    {
        $allowed = ['pending:scheduled', 'pending:in_progress', 'pending:canceled', 'scheduled:in_progress', 'scheduled:completed', 'scheduled:canceled', 'in_progress:completed', 'in_progress:canceled'];
        $cases = [];
        foreach (MaintenanceRequestStatus::cases() as $from) {
            foreach (MaintenanceRequestStatus::cases() as $to) {
                $cases[$from->value.':'.$to->value] = [$from->value, $to->value, in_array($from->value.':'.$to->value, $allowed, true)];
            }
        }

        return $cases;
    }

    public function test_edit_records_old_new_values_and_does_not_confuse_author_and_responsible(): void
    {
        $author = User::factory()->admin()->create();
        $responsible = User::factory()->admin()->create();
        $replacement = User::factory()->admin()->create();
        $old = ServiceProvider::factory()->create();
        $new = ServiceProvider::factory()->create();
        $maintenance = MaintenanceRequest::factory()->scheduled()->create(['admin_id' => $responsible->id, 'service_provider_id' => $old->id]);
        $data = ['admin_id' => $replacement->id, 'service_provider_id' => $new->id, 'scheduled_at' => now()->addDays(2)->toDateTimeString(), 'description' => 'Nova descrição', 'cost' => '99.90'];
        $this->actingAs($author)->patch(route('admin.maintenances.update', $maintenance), $data)->assertRedirect();
        $history = MaintenanceRequestChange::sole();
        $this->assertSame($author->id, $history->changed_by_user_id);
        $this->assertSame(UserRole::Admin, $history->actor_role);
        $this->assertSame($responsible->id, $history->changes['admin_id']['old']);
        $this->assertSame($replacement->id, $history->changes['admin_id']['new']);
        $this->assertSame($old->name, $history->changes['service_provider_id']['old_label']);
        $this->assertCount(5, $history->changes);
        $this->assertDatabaseCount('maintenance_request_status_histories', 0);
        $updatedAt = $maintenance->fresh()->updated_at;
        $this->travel(1)->minute();
        $this->patch(route('admin.maintenances.update', $maintenance), $data)->assertRedirect();
        $this->assertDatabaseCount('maintenance_request_changes', 1);
        $this->assertTrue($updatedAt->eq($maintenance->fresh()->updated_at));
        $old->delete();
        $new->delete();
        $this->get(route('admin.maintenances.show', $maintenance))->assertInertia(fn (Assert $page) => $page->has('maintenance.history', 1)->where('maintenance.provider_archived', true));
    }

    #[DataProvider('invalidData')]
    public function test_invalid_updates_leave_business_state_and_history_unchanged(array $data, string $field, string $state): void
    {
        $maintenance = MaintenanceRequest::factory()->create(['status' => $state]);
        $before = $maintenance->refresh()->getRawOriginal();
        $this->actingAs(User::factory()->admin()->create())->patchJson(route('admin.maintenances.update', $maintenance), $data)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($before, $maintenance->fresh()->getRawOriginal());
        $this->assertDatabaseCount('maintenance_request_changes', 0);
        $this->assertDatabaseCount('maintenance_request_status_histories', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public static function invalidData(): array
    {
        return [
            [['status' => 'scheduled'], 'service_provider_id', 'pending'],
            [['status' => 'completed'], 'service_provider_id', 'in_progress'],
            [['admin_id' => 999999], 'admin_id', 'pending'],
            [['service_provider_id' => 999999], 'service_provider_id', 'pending'],
            [['scheduled_at' => 'invalid'], 'scheduled_at', 'pending'],
            [['scheduled_at' => '2000-01-01'], 'scheduled_at', 'pending'],
            [['cost' => '-1'], 'cost', 'pending'], [['cost' => '1.001'], 'cost', 'pending'], [['cost' => '100000000.00'], 'cost', 'pending'], [['cost' => '1e3'], 'cost', 'pending'],
            [['unit_id' => 99999], 'unit_id', 'pending'], [['incident_id' => 99999], 'incident_id', 'pending'], [['resident_id' => 99999], 'resident_id', 'pending'],
            [['responsible_admin_id' => 99999], 'responsible_admin_id', 'pending'], [['executed_at' => '2100-01-01'], 'executed_at', 'in_progress'], [['changed_by_user_id' => 99999], 'changed_by_user_id', 'pending'],
            [['description' => 'Reabrir'], 'status', 'completed'],
        ];
    }

    public function test_schedule_reuses_valid_data_and_execution_rejects_future_schedule(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = ServiceProvider::factory()->create();
        $maintenance = MaintenanceRequest::factory()->create(['service_provider_id' => $provider->id, 'scheduled_at' => now()->addDay()]);
        $this->actingAs($admin)->patch(route('admin.maintenances.update', $maintenance), ['status' => 'scheduled'])->assertRedirect();
        $this->patchJson(route('admin.maintenances.update', $maintenance), ['status' => 'completed'])->assertUnprocessable()->assertJsonValidationErrors('scheduled_at');
        $this->patch(route('admin.maintenances.update', $maintenance), ['status' => 'completed', 'scheduled_at' => now()->toDateTimeString()])->assertRedirect();
        $this->assertNotNull($maintenance->fresh()->executed_at);
        $pending = MaintenanceRequest::factory()->create(['service_provider_id' => $provider->id]);
        $this->patchJson(route('admin.maintenances.update', $pending), ['status' => 'scheduled'])->assertUnprocessable()->assertJsonValidationErrors('scheduled_at');
    }

    public function test_creation_rejects_invalid_unit_responsible_and_injected_relationships(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = Unit::factory()->create();
        $base = ['description' => 'Reparo', 'unit_id' => $unit->id, 'admin_id' => $admin->id];
        $this->actingAs($admin);
        foreach ([User::factory()->morador()->create(), User::factory()->admin()->create(['is_active' => false])] as $invalid) {
            $this->postJson(route('admin.maintenances.store'), array_replace($base, ['admin_id' => $invalid->id]))->assertUnprocessable()->assertJsonValidationErrors('admin_id');
        }
        foreach ([['unit_id' => 99999], ['incident_id' => 99999], ['resident_id' => 99999], ['status' => 'completed']] as $data) {
            $this->postJson(route('admin.maintenances.store'), array_replace($base, $data))->assertUnprocessable();
        }
        $unit->update(['status' => 'inactive']);
        $this->postJson(route('admin.maintenances.store'), $base)->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        $this->assertDatabaseCount('maintenance_requests', 0);
    }

    #[DataProvider('blockedRoles')]
    public function test_non_admins_are_blocked_on_every_management_route(string $role): void
    {
        $maintenance = MaintenanceRequest::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role]));
        foreach (['index', 'create'] as $route) {
            $this->get(route('admin.maintenances.'.$route))->assertForbidden();
        }
        $this->get(route('admin.maintenances.show', $maintenance))->assertForbidden();
        $this->postJson(route('admin.maintenances.store'), [])->assertForbidden();
        $this->patchJson(route('admin.maintenances.update', $maintenance), ['status' => 'canceled'])->assertForbidden();
    }

    public static function blockedRoles(): array
    {
        return [['morador'], ['porteiro']];
    }

    public function test_direct_factory_has_no_resident_and_is_visible_only_to_administration(): void
    {
        $maintenance = MaintenanceRequest::factory()->administrativeDirect()->create();
        $this->assertNull($maintenance->resident_id);
        $this->assertNull($maintenance->incident_id);
        $this->assertNotNull($maintenance->unit);
        $this->actingAs($maintenance->admin)->get(route('admin.maintenances.show', $maintenance))->assertOk();
        $this->actingAs(User::factory()->morador()->create(['unit_id' => $maintenance->unit_id]))->get(route('admin.maintenances.show', $maintenance))->assertForbidden();
    }

    public function test_string_form_identifiers_do_not_invent_assignments_or_reject_existing_archived_provider(): void
    {
        $maintenance = MaintenanceRequest::factory()->inProgress()->create();
        $maintenance->serviceProvider->delete();
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.maintenances.update', $maintenance), [
            'admin_id' => (string) $maintenance->admin_id,
            'service_provider_id' => (string) $maintenance->service_provider_id,
            'description' => 'Descrição revisada',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(['description'], array_keys(MaintenanceRequestChange::sole()->changes));
        $this->assertSame($maintenance->admin_id, $maintenance->fresh()->admin_id);
    }

    public function test_guest_inactive_and_unverified_are_blocked_before_management(): void
    {
        $this->get(route('admin.maintenances.index'))->assertRedirect(route('login'));
        $this->get(route('admin.service-providers.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->admin()->unverified()->create())->get(route('admin.maintenances.index'))->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->admin()->create(['is_active' => false]))->get(route('admin.maintenances.index'))->assertRedirect(route('login'));
    }
}
