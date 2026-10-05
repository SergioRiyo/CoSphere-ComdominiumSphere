<?php

namespace Tests\Feature;

use App\Enums\IncidentType;
use App\Enums\MaintenanceRequestStatus;
use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestStatusHistory;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Services\MaintenanceRequestService;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class MaintenanceRequestDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_can_create_independent_pending_request_without_assignment(): void
    {
        $resident = User::factory()->morador()->create();
        $other = User::factory()->morador()->create();
        $request = app(MaintenanceRequestService::class)->create($resident, [
            'description' => 'Consertar a torneira', 'resident_id' => $other->id, 'unit_id' => $other->unit_id,
            'status' => 'completed', 'service_provider_id' => 99, 'admin_id' => $other->id,
            'scheduled_at' => now(), 'executed_at' => now(), 'cost' => 999,
        ])->refresh();
        $this->assertSame(MaintenanceRequestStatus::Pending, $request->status);
        $this->assertNull($request->incident);
        $this->assertNull($request->serviceProvider);
        $this->assertNull($request->admin);
        $this->assertNull($request->scheduled_at);
        $this->assertNull($request->executed_at);
        $this->assertNull($request->cost);
        $this->assertSame($resident->id, $request->resident_id);
        $this->assertSame($resident->unit_id, $request->unit_id);
        $this->assertSame($resident->id, $request->resident->id);
        $this->assertSame($resident->unit_id, $request->unit->id);
        $this->assertSame($request->id, $resident->requestedMaintenanceRequests()->sole()->id);
        $this->assertSame($request->id, $request->unit->maintenanceRequests()->sole()->id);
        $history = $request->statusHistory()->sole();
        $this->assertNull($history->from_status);
        $this->assertSame(MaintenanceRequestStatus::Pending, $history->to_status);
        $this->assertSame(UserRole::Morador, $history->actor_role);
        $this->assertSame($resident->id, $history->changedBy->id);
        $this->assertDatabaseCount('incidents', 0);
        $this->assertDatabaseCount('service_providers', 0);
        $this->assertSame(MaintenanceRequestStatus::Pending, (new MaintenanceRequest)->status);
    }

    public function test_linking_requires_the_exact_resident_and_unit_and_does_not_reclassify_incident(): void
    {
        $incident = Incident::factory()->create(['category' => 'maintenance']);
        $request = app(MaintenanceRequestService::class)->create($incident->resident, [
            'description' => 'Consertar', 'incident_id' => $incident->id,
        ]);
        $this->assertSame($incident->id, $request->incident->id);
        $this->assertSame($incident->resident_id, $request->resident_id);
        $this->assertSame($incident->unit_id, $request->unit_id);
        $this->assertSame(IncidentType::Incident, $incident->refresh()->type);
        $other = User::factory()->morador()->create(['unit_id' => $incident->unit_id]);
        $this->expectException(AuthorizationException::class);
        app(MaintenanceRequestService::class)->create($other, ['description' => 'Consertar', 'incident_id' => $incident->id]);
    }

    public function test_independent_context_keeps_authorization_after_resident_changes_unit(): void
    {
        $request = MaintenanceRequest::factory()->withoutIncident()->create();
        $other = User::factory()->morador()->create(['unit_id' => $request->unit_id]);
        $this->assertTrue(Gate::forUser($request->resident)->allows('view', $request));
        $this->assertFalse(Gate::forUser($other)->allows('view', $request));
        $this->assertFalse(Gate::forUser(User::factory()->porteiro()->create())->allows('view', $request));
        $this->assertTrue(Gate::forUser(User::factory()->admin()->create())->allows('view', $request));
        $oldUnit = $request->unit_id;
        $request->resident->update(['unit_id' => User::factory()->morador()->create()->unit_id]);
        $this->assertSame($oldUnit, $request->refresh()->unit_id);
        $this->assertTrue(Gate::forUser($request->resident->fresh())->allows('view', $request));
    }

    #[DataProvider('transitions')]
    public function test_maintenance_uses_its_own_matrix_and_dates(MaintenanceRequestStatus $from, MaintenanceRequestStatus $to, bool $allowed): void
    {
        $this->travelTo(now()->startOfSecond());
        $admin = User::factory()->admin()->create();
        $request = match ($from) {
            MaintenanceRequestStatus::Pending => MaintenanceRequest::factory()->pendingWithoutProvider()->create(),
            MaintenanceRequestStatus::Scheduled => MaintenanceRequest::factory()->scheduled()->create(['scheduled_at' => now()]),
            MaintenanceRequestStatus::InProgress => MaintenanceRequest::factory()->inProgress()->create(),
            MaintenanceRequestStatus::Completed => MaintenanceRequest::factory()->completed()->create(),
            MaintenanceRequestStatus::Canceled => MaintenanceRequest::factory()->canceled()->create(),
        };
        $before = $request->refresh()->getRawOriginal();
        $this->travel(1)->hour();
        $provider = ServiceProvider::factory()->create();
        try {
            $updated = app(MaintenanceRequestService::class)->transition($admin, $request, $to, [
                'service_provider_id' => $provider->id, 'scheduled_at' => now()->toDateTimeString(),
                'cost' => '125.6', 'executed_at' => '2000-01-01', 'admin_id' => $request->resident_id,
            ], 'Manutenção verificada');
            $this->assertTrue($allowed, 'A forbidden transition succeeded.');
            $this->assertSame($to, $updated->status);
            $this->assertSame($admin->id, $updated->admin_id);
            $history = $request->statusHistory()->sole();
            $this->assertSame($from, $history->from_status);
            $this->assertSame($to, $history->to_status);
            $this->assertSame($admin->id, $history->changed_by_user_id);
            $this->assertSame(UserRole::Admin, $history->actor_role);
            $this->assertSame('Manutenção verificada', $history->reason);
            $this->assertSame(now()->toDateTimeString(), $history->created_at->toDateTimeString());
            $this->assertSame($request->id, $history->maintenanceRequest->id);
            if ($to === MaintenanceRequestStatus::Completed) {
                $this->assertSame('125.60', $updated->cost);
                $this->assertSame(now()->toDateTimeString(), $updated->executed_at->toDateTimeString());
                $this->assertTrue($updated->executed_at->greaterThanOrEqualTo($updated->scheduled_at));
            } else {
                $this->assertNull($updated->executed_at);
                $this->assertNull($updated->cost);
            }
        } catch (ValidationException) {
            $this->assertFalse($allowed, 'A permitted transition failed.');
            $this->assertSame($before, $request->refresh()->getRawOriginal());
            $this->assertDatabaseCount('maintenance_request_status_histories', 0);
        }
    }

    public static function transitions(): array
    {
        $cases = [];
        $allowed = ['pending:scheduled', 'pending:canceled', 'scheduled:in_progress', 'scheduled:canceled', 'in_progress:completed', 'in_progress:canceled'];
        foreach (MaintenanceRequestStatus::cases() as $from) {
            foreach (MaintenanceRequestStatus::cases() as $to) {
                $cases[$from->value.':'.$to->value] = [$from, $to, in_array($from->value.':'.$to->value, $allowed, true)];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidExecution')]
    public function test_schedule_and_execution_validation_leave_the_entity_unchanged(string $scenario): void
    {
        $admin = User::factory()->admin()->create();
        $request = match ($scenario) {
            'future_execution' => MaintenanceRequest::factory()->scheduled()->create(),
            'negative_cost' => MaintenanceRequest::factory()->inProgress()->create(),
            default => MaintenanceRequest::factory()->pendingWithoutProvider()->create(),
        };
        $before = $request->refresh()->getRawOriginal();
        $target = match ($scenario) {
            'future_execution' => MaintenanceRequestStatus::InProgress,
            'negative_cost' => MaintenanceRequestStatus::Completed,
            default => MaintenanceRequestStatus::Scheduled,
        };
        $provider = ServiceProvider::factory()->create();
        $data = match ($scenario) {
            'missing_provider' => ['scheduled_at' => now()],
            'invalid_provider' => ['scheduled_at' => now(), 'service_provider_id' => 99999],
            'missing_date' => ['service_provider_id' => $provider->id],
            'before_opening' => ['scheduled_at' => now()->subDay(), 'service_provider_id' => $provider->id],
            'negative_cost' => ['cost' => -1],
            default => [],
        };
        try {
            app(MaintenanceRequestService::class)->transition($admin, $request, $target, $data);
            $this->fail('Expected invalid schedule or execution.');
        } catch (ValidationException) {
            $this->assertSame($before, $request->refresh()->getRawOriginal());
            $this->assertDatabaseCount('maintenance_request_status_histories', 0);
        }
    }

    public static function invalidExecution(): array
    {
        return [['missing_provider'], ['invalid_provider'], ['missing_date'], ['before_opening'], ['future_execution'], ['negative_cost']];
    }

    #[DataProvider('atomicOperations')]
    public function test_history_failure_reverts_creation_status_dates_assignment_and_cost(string $operation, bool $throw): void
    {
        $actor = $operation === 'create' ? User::factory()->morador()->create() : User::factory()->admin()->create();
        $request = match ($operation) {
            'create' => null,
            'complete' => MaintenanceRequest::factory()->inProgress()->create(),
            default => MaintenanceRequest::factory()->create(),
        };
        $before = $request?->refresh()->getRawOriginal();
        $provider = ServiceProvider::factory()->create();
        $baseline = DB::transactionLevel();
        $inTransaction = false;
        MaintenanceRequestStatusHistory::creating(function () use (&$inTransaction, $baseline, $throw): bool {
            $inTransaction = DB::transactionLevel() > $baseline;
            if ($throw) {
                throw new RuntimeException('history failed');
            }

            return false;
        });
        try {
            $service = app(MaintenanceRequestService::class);
            match ($operation) {
                'create' => $service->create($actor, ['description' => 'Manutenção']),
                'schedule' => $service->transition($actor, $request, MaintenanceRequestStatus::Scheduled, ['scheduled_at' => now(), 'service_provider_id' => $provider->id]),
                'complete' => $service->transition($actor, $request, MaintenanceRequestStatus::Completed, ['cost' => 100]),
                'cancel' => $service->transition($actor, $request, MaintenanceRequestStatus::Canceled),
            };
            $this->fail('Expected history failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame($throw ? 'history failed' : 'Não foi possível persistir o histórico da manutenção.', $exception->getMessage());
        } finally {
            MaintenanceRequestStatusHistory::flushEventListeners();
        }
        $this->assertTrue($inTransaction);
        $this->assertSame($baseline, DB::transactionLevel());
        $this->assertDatabaseCount('maintenance_request_status_histories', 0);
        $this->assertDatabaseCount('maintenance_requests', $request === null ? 0 : 1);
        if ($request !== null) {
            $this->assertSame($before, $request->refresh()->getRawOriginal());
        }
    }

    public static function atomicOperations(): array
    {
        return [['create', true], ['schedule', true], ['complete', true], ['cancel', true], ['create', false], ['schedule', false], ['complete', false], ['cancel', false]];
    }

    public function test_history_is_deterministic_and_stale_terminal_state_cannot_be_overwritten(): void
    {
        $this->travelTo(now()->startOfSecond());
        $resident = User::factory()->morador()->create();
        $admin = User::factory()->admin()->create();
        $provider = ServiceProvider::factory()->create();
        $service = app(MaintenanceRequestService::class);
        $request = $service->create($resident, ['description' => 'Manutenção']);
        $original = $request->statusHistory()->sole()->getRawOriginal();
        $service->transition($admin, $request, MaintenanceRequestStatus::Scheduled, ['scheduled_at' => now(), 'service_provider_id' => $provider->id]);
        $service->transition($admin, $request, MaintenanceRequestStatus::InProgress);
        $service->transition($admin, $request, MaintenanceRequestStatus::Completed);
        $history = $request->statusHistory()->get();
        $this->assertSame($original, $history[0]->getRawOriginal());
        $this->assertSame([MaintenanceRequestStatus::Pending, MaintenanceRequestStatus::Scheduled, MaintenanceRequestStatus::InProgress, MaintenanceRequestStatus::Completed], $history->pluck('to_status')->all());
        $this->assertSame([null, MaintenanceRequestStatus::Pending, MaintenanceRequestStatus::Scheduled, MaintenanceRequestStatus::InProgress], $history->pluck('from_status')->all());
        $this->assertSame($history->sortBy('id')->modelKeys(), $history->modelKeys());
        User::whereKey($admin->id)->update(['role' => UserRole::Porteiro]);
        $this->assertSame(UserRole::Admin, $history[1]->actor_role);
        $admin = User::factory()->admin()->create();
        $this->expectException(ValidationException::class);
        $service->transition($admin, $request, MaintenanceRequestStatus::Canceled);
    }

    public function test_only_current_active_verified_admin_can_transition(): void
    {
        $request = MaintenanceRequest::factory()->create();
        $before = $request->refresh()->getRawOriginal();
        $admin = User::factory()->admin()->create();
        User::whereKey($admin->id)->update(['is_active' => false]);
        foreach ([$request->resident, User::factory()->porteiro()->create(), User::factory()->admin()->unverified()->create(), $admin] as $actor) {
            try {
                app(MaintenanceRequestService::class)->transition($actor, $request, MaintenanceRequestStatus::Canceled);
                $this->fail('Unauthorized actor changed maintenance.');
            } catch (AuthorizationException) {
                $this->assertSame($before, $request->refresh()->getRawOriginal());
                $this->assertDatabaseCount('maintenance_request_status_histories', 0);
            }
        }
    }

    public function test_dates_money_and_nullable_relations_are_cast_correctly(): void
    {
        $request = MaintenanceRequest::factory()->completed()->create(['cost' => 123.4])->refresh();
        $this->assertInstanceOf(CarbonInterface::class, $request->scheduled_at);
        $this->assertInstanceOf(CarbonInterface::class, $request->executed_at);
        $this->assertSame('123.40', $request->cost);
        $this->assertNull($request->incident);
        $request->serviceProvider->delete();
        $this->assertNotNull($request->fresh()->serviceProvider);
    }

    public function test_soft_deletes_preserve_history_and_related_incident(): void
    {
        $incident = Incident::factory()->create();
        $request = MaintenanceRequest::factory()->linkedToIncident($incident)->create();
        app(MaintenanceRequestService::class)->transition(User::factory()->admin()->create(), $request, MaintenanceRequestStatus::Canceled);
        $history = $request->statusHistory()->sole();
        $incident->delete();
        $request->delete();
        $this->assertSoftDeleted($incident);
        $this->assertSoftDeleted($request);
        $this->assertModelExists($history);
        $this->assertSame($request->id, $history->fresh()->maintenanceRequest->id);
        $this->assertSame($incident->id, $request->fresh()->incident->id);
    }

    public function test_foreign_keys_retain_owner_unit_history_and_incident_and_allow_provider_deletion(): void
    {
        $history = MaintenanceRequestStatusHistory::factory()->create();
        $request = $history->maintenanceRequest;
        foreach ([$history->changedBy, $request->resident, $request->unit, $request] as $model) {
            try {
                DB::transaction(fn () => $model instanceof MaintenanceRequest ? $model->forceDelete() : $model->delete());
                $this->fail('Historical or ownership reference deleted.');
            } catch (QueryException) {
                $this->assertModelExists($model);
            }
        }
        $provider = $request->serviceProvider;
        $provider->forceDelete();
        $this->assertNull($request->refresh()->service_provider_id);
        $this->assertNull($request->serviceProvider);
        $this->assertModelExists($history);
    }
}
