<?php

namespace Tests\Feature;

use App\Enums\IncidentCategory;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\IncidentStatusHistory;
use App\Models\User;
use App\Services\IncidentService;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class IncidentDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_enforces_defaults_and_owner_from_trusted_context(): void
    {
        $this->freezeTime();
        $actor = User::factory()->morador()->create();
        $other = User::factory()->morador()->create();
        $incident = app(IncidentService::class)->create($actor, $this->data() + [
            'status' => 'completed', 'priority' => 'high', 'resident_id' => $other->id,
            'unit_id' => $other->unit_id, 'opened_at' => '2000-01-01', 'changed_by_user_id' => $other->id,
        ])->refresh();
        $this->assertSame(IncidentStatus::Open, $incident->status);
        $this->assertSame(IncidentPriority::Medium, $incident->priority);
        $this->assertSame(IncidentType::Incident, $incident->type);
        $this->assertSame($actor->id, $incident->resident_id);
        $this->assertSame($actor->unit_id, $incident->unit_id);
        $this->assertSame(now()->toDateTimeString(), $incident->opened_at->toDateTimeString());
        $this->assertSame($actor->id, $incident->resident->id);
        $this->assertSame($actor->unit_id, $incident->unit->id);
        $this->assertSame($incident->id, $actor->reportedIncidents()->sole()->id);
        $history = $incident->statusHistory()->sole();
        $this->assertNull($history->from_status);
        $this->assertSame(IncidentStatus::Open, $history->to_status);
        $this->assertSame($actor->id, $history->changedBy->id);
        $this->assertSame(UserRole::Morador, $history->actor_role);
        $this->assertSame(now()->toDateTimeString(), $history->created_at->toDateTimeString());
    }

    public function test_database_and_model_share_status_priority_and_type_defaults(): void
    {
        $actor = User::factory()->morador()->create();
        $new = new Incident;
        $this->assertSame(IncidentStatus::Open, $new->status);
        $this->assertSame(IncidentPriority::Medium, $new->priority);
        $this->assertSame(IncidentType::Incident, $new->type);
        $id = DB::table('incidents')->insertGetId($this->data() + ['resident_id' => $actor->id, 'unit_id' => $actor->unit_id]);
        $incident = Incident::findOrFail($id);
        $this->assertSame($new->status, $incident->status);
        $this->assertSame($new->priority, $incident->priority);
        $this->assertSame($new->type, $incident->type);
        $this->assertInstanceOf(CarbonInterface::class, $incident->opened_at);
        $this->assertDatabaseCount('incident_status_histories', 0);
    }

    public function test_type_is_independent_from_category_and_categories_keep_stable_values(): void
    {
        $actor = User::factory()->morador()->create();
        foreach (IncidentCategory::cases() as $category) {
            $incident = app(IncidentService::class)->create($actor, array_replace($this->data(), ['category' => $category->value]));
            $this->assertSame($category, $incident->refresh()->category);
            $this->assertSame($category->value, $incident->getRawOriginal('category'));
            $this->assertSame(IncidentType::Incident, $incident->type);
        }
        $request = app(IncidentService::class)->create($actor, $this->data() + ['type' => 'maintenance_request']);
        $this->assertSame(IncidentCategory::Security, $request->category);
        $this->assertSame(IncidentType::MaintenanceRequest, $request->type);
    }

    public function test_unknown_legacy_category_remains_readable_serializable_and_unchanged(): void
    {
        $incident = Incident::factory()->create(['category' => 'legacy/electrical']);
        $this->assertSame('legacy/electrical', $incident->refresh()->category);
        $this->assertSame('legacy/electrical', json_decode($incident->toJson(), true)['category']);
        app(IncidentService::class)->transition(User::factory()->admin()->create(), $incident, IncidentStatus::InProgress);
        $this->assertSame('legacy/electrical', $incident->refresh()->getRawOriginal('category'));
    }

    #[DataProvider('invalidClassification')]
    public function test_new_unknown_categories_and_types_are_rejected(array $changes): void
    {
        $actor = User::factory()->morador()->create();
        $this->expectException(ValidationException::class);
        app(IncidentService::class)->create($actor, array_replace($this->data(), $changes));
    }

    public static function invalidClassification(): array
    {
        return [[['category' => 'unknown']], [['type' => 'unknown']], [['category' => null]]];
    }

    #[DataProvider('transitions')]
    public function test_transition_matrix_is_enforced_without_fictitious_events(IncidentStatus $from, IncidentStatus $to, bool $allowed): void
    {
        $this->freezeTime();
        $admin = User::factory()->admin()->create();
        $incident = Incident::factory()->create(['status' => $from]);
        $before = $incident->refresh()->getRawOriginal();
        $this->travel(1)->hour();
        try {
            $updated = app(IncidentService::class)->transition($admin, $incident, $to, 'Inspeção');
            $this->assertTrue($allowed, 'A forbidden transition succeeded.');
            $this->assertSame($to, $updated->status);
            $history = $incident->statusHistory()->sole();
            $this->assertSame($from, $history->from_status);
            $this->assertSame($to, $history->to_status);
            $this->assertSame($admin->id, $history->changed_by_user_id);
            $this->assertSame(UserRole::Admin, $history->actor_role);
            $this->assertSame('Inspeção', $history->reason);
            $this->assertSame(now()->toDateTimeString(), $history->created_at->toDateTimeString());
            $this->assertSame($incident->id, $history->incident->id);
        } catch (ValidationException) {
            $this->assertFalse($allowed, 'A valid transition failed.');
            $this->assertSame($before, $incident->refresh()->getRawOriginal());
            $this->assertDatabaseCount('incident_status_histories', 0);
        }
    }

    public static function transitions(): array
    {
        $cases = [];
        $allowed = ['open:in_progress', 'open:canceled', 'in_progress:completed', 'in_progress:canceled'];
        foreach (IncidentStatus::cases() as $from) {
            foreach (IncidentStatus::cases() as $to) {
                $cases[$from->value.':'.$to->value] = [$from, $to, in_array($from->value.':'.$to->value, $allowed, true)];
            }
        }

        return $cases;
    }

    public function test_history_is_append_only_ordered_by_date_and_id_with_actor_role_snapshot(): void
    {
        $this->freezeTime();
        $resident = User::factory()->morador()->create();
        $admin = User::factory()->admin()->create();
        $service = app(IncidentService::class);
        $incident = $service->create($resident, $this->data());
        $original = $incident->statusHistory()->sole()->getRawOriginal();
        $service->transition($admin, $incident, IncidentStatus::InProgress);
        $service->transition($admin, $incident, IncidentStatus::Completed);
        User::whereKey($admin->id)->update(['role' => UserRole::Morador]);
        $history = $incident->statusHistory()->get();
        $this->assertSame($original, $history[0]->getRawOriginal());
        $this->assertSame([IncidentStatus::Open, IncidentStatus::InProgress, IncidentStatus::Completed], $history->pluck('to_status')->all());
        $this->assertSame([null, IncidentStatus::Open, IncidentStatus::InProgress], $history->pluck('from_status')->all());
        $this->assertSame(UserRole::Admin, $history[1]->actor_role);
        $this->assertSame($history->sortBy('id')->modelKeys(), $history->modelKeys());
    }

    #[DataProvider('atomicOperations')]
    public function test_history_insert_failure_rolls_back_status_timestamps_and_creation(string $operation, bool $throw): void
    {
        $actor = $operation === 'create' ? User::factory()->morador()->create() : User::factory()->admin()->create();
        $incident = $operation === 'create' ? null : Incident::factory()->create();
        $before = $incident?->refresh()->getRawOriginal();
        $baseline = DB::transactionLevel();
        $inTransaction = false;
        IncidentStatusHistory::creating(function () use (&$inTransaction, $baseline, $throw): bool {
            $inTransaction = DB::transactionLevel() > $baseline;
            if ($throw) {
                throw new RuntimeException('history failed');
            }

            return false;
        });
        try {
            $service = app(IncidentService::class);
            $operation === 'create' ? $service->create($actor, $this->data())
                : $service->transition($actor, $incident, IncidentStatus::InProgress);
            $this->fail('Expected history failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame($throw ? 'history failed' : 'Não foi possível persistir o histórico da ocorrência.', $exception->getMessage());
        } finally {
            IncidentStatusHistory::flushEventListeners();
        }
        $this->assertTrue($inTransaction);
        $this->assertSame($baseline, DB::transactionLevel());
        $this->assertDatabaseCount('incident_status_histories', 0);
        $this->assertDatabaseCount('incidents', $incident === null ? 0 : 1);
        if ($incident !== null) {
            $this->assertSame($before, $incident->refresh()->getRawOriginal());
        }
    }

    public static function atomicOperations(): array
    {
        return [['create', true], ['transition', true], ['create', false], ['transition', false]];
    }

    public function test_priority_can_only_be_changed_by_an_authorized_current_admin(): void
    {
        $incident = Incident::factory()->create();
        $service = app(IncidentService::class);
        try {
            $service->updatePriority($incident->resident, $incident, IncidentPriority::High);
            $this->fail('Residents must not change priority.');
        } catch (AuthorizationException) {
            $this->assertSame(IncidentPriority::Medium, $incident->refresh()->priority);
        }
        $admin = User::factory()->admin()->create();
        $service->updatePriority($admin, $incident, IncidentPriority::High);
        $this->assertSame(IncidentPriority::High, $incident->refresh()->priority);
        User::whereKey($admin->id)->update(['is_active' => false]);
        $this->expectException(AuthorizationException::class);
        $service->updatePriority($admin, $incident, IncidentPriority::Low);
    }

    #[DataProvider('unauthorizedActors')]
    public function test_invalid_actors_cannot_create(string $state): void
    {
        $actor = match ($state) {
            'admin' => User::factory()->admin()->create(),
            'doorman' => User::factory()->porteiro()->create(),
            'inactive' => User::factory()->morador()->inactive()->create(),
            'unverified' => User::factory()->morador()->unverified()->create(),
            'no_unit' => User::factory()->morador()->create(['unit_id' => null]),
        };
        $this->expectException(AuthorizationException::class);
        app(IncidentService::class)->create($actor, $this->data());
    }

    public static function unauthorizedActors(): array
    {
        return [['admin'], ['doorman'], ['inactive'], ['unverified'], ['no_unit']];
    }

    public function test_stale_entity_and_actor_are_reloaded_before_transition(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = Incident::factory()->create();
        $service = app(IncidentService::class);
        $service->transition($admin, $incident, IncidentStatus::Canceled);
        try {
            $service->transition($admin, $incident, IncidentStatus::InProgress);
            $this->fail('A stale model must not revive a canceled incident.');
        } catch (ValidationException) {
            $this->assertSame(IncidentStatus::Canceled, $incident->refresh()->status);
            $this->assertDatabaseCount('incident_status_histories', 1);
        }
        $other = Incident::factory()->create();
        User::whereKey($admin->id)->update(['role' => UserRole::Porteiro]);
        $this->expectException(AuthorizationException::class);
        $service->transition($admin, $other, IncidentStatus::InProgress);
    }

    public function test_transition_rejects_resident_doorman_and_ineligible_admins_without_changes(): void
    {
        $incident = Incident::factory()->create()->refresh();
        $before = $incident->getRawOriginal();
        foreach ([$incident->resident, User::factory()->porteiro()->create(), User::factory()->admin()->inactive()->create(), User::factory()->admin()->unverified()->create()] as $actor) {
            try {
                app(IncidentService::class)->transition($actor, $incident, IncidentStatus::InProgress);
                $this->fail('Unauthorized transition succeeded.');
            } catch (AuthorizationException) {
                $this->assertSame($before, $incident->refresh()->getRawOriginal());
                $this->assertDatabaseCount('incident_status_histories', 0);
            }
        }
    }

    public function test_priority_write_failure_leaves_persisted_priority_unchanged(): void
    {
        $incident = Incident::factory()->create()->refresh();
        $admin = User::factory()->admin()->create();
        $before = $incident->getRawOriginal();
        Incident::updating(fn (): bool => false);
        try {
            app(IncidentService::class)->updatePriority($admin, $incident, IncidentPriority::High);
            $this->fail('Expected priority write failure.');
        } catch (RuntimeException) {
            $this->assertSame($before, $incident->refresh()->getRawOriginal());
        } finally {
            Incident::flushEventListeners();
        }
    }

    public function test_incident_factory_states_are_coherent_and_follow_resident_unit(): void
    {
        $resident = User::factory()->morador()->create();
        $associated = Incident::factory()->for($resident, 'resident')->create();
        $this->assertSame($resident->unit_id, $associated->unit_id);
        foreach (['open' => IncidentStatus::Open, 'inProgress' => IncidentStatus::InProgress, 'completed' => IncidentStatus::Completed, 'canceled' => IncidentStatus::Canceled] as $state => $status) {
            $incident = Incident::factory()->{$state}()->create(['resident_id' => $resident->id]);
            $this->assertSame($resident->unit_id, $incident->unit_id);
            $this->assertSame($status, $incident->status);
            $this->assertSame(IncidentPriority::Medium, $incident->priority);
            $this->assertTrue($incident->opened_at->lessThanOrEqualTo($incident->created_at));
        }
    }

    public function test_history_foreign_keys_retain_actor_and_entity(): void
    {
        $history = IncidentStatusHistory::factory()->create();
        foreach ([$history->changedBy, $history->incident] as $model) {
            try {
                DB::transaction(fn () => $model instanceof Incident ? $model->forceDelete() : $model->delete());
                $this->fail('Historical references must be retained.');
            } catch (QueryException) {
                $this->assertModelExists($model);
            }
        }
        $this->assertModelExists($history);
    }

    private function data(): array
    {
        return ['title' => 'Portão avariado', 'description' => 'O portão não fecha.', 'category' => 'security'];
    }
}
