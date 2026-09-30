<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;
use App\Models\User;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ReservationHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-30 10:00:00'));
    }

    #[DataProvider('approvalModes')]
    public function test_initial_history_uses_actual_initial_status_and_backend_actor(bool $manual): void
    {
        $resident = User::factory()->morador()->create();
        $area = CommonArea::factory()->create(['requires_approval' => $manual]);
        $this->actingAs($resident)->postJson(route('morador.reservations.store'), $this->data($area) + [
            'changed_by_user_id' => 99999, 'actor_role' => 'admin', 'from_status' => 'confirmed',
            'to_status' => 'cancelled', 'created_at' => '2000-01-01',
        ])->assertCreated();
        $history = ReservationStatusHistory::sole();
        $this->assertNull($history->from_status);
        $this->assertSame($manual ? ReservationStatus::Pending : ReservationStatus::Approved, $history->to_status);
        $this->assertTrue($history->changedBy->is($resident));
        $this->assertTrue($history->reservation->is(Reservation::sole()));
        $this->assertSame(UserRole::Morador, $history->actor_role);
        $this->assertTrue($history->created_at->equalTo(now()));
        $this->assertNull($history->reason);
        $this->assertDatabaseCount('notifications', 1);
    }

    public static function approvalModes(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('transitions')]
    public function test_exactly_one_history_per_valid_transition_and_none_per_invalid_attempt(string $action, ReservationStatus $from, bool $valid, ReservationStatus $target): void
    {
        $reservation = Reservation::factory()->create($this->period() + ['status' => $from]);
        $actor = $action === 'residentCancel' ? $reservation->user : User::factory()->admin()->create();
        $response = $this->actingAs($actor)->patchJson($this->url($action, $reservation), [
            'rejection_reason' => '  Motivo real  ', 'changed_by_user_id' => 99999,
            'actor_role' => 'porteiro', 'from_status' => 'completed', 'to_status' => 'completed',
            'reason' => 'Injetado', 'created_at' => '2000-01-01',
        ]);
        if (! $valid) {
            $response->assertUnprocessable();
            $this->assertDatabaseCount('reservation_status_histories', 0);
            $this->assertSame($from, $reservation->refresh()->status);

            return;
        }
        $response->assertOk();
        $history = $reservation->statusHistory()->sole();
        $this->assertSame($from, $history->from_status);
        $this->assertSame($target, $history->to_status);
        $this->assertSame($target, $reservation->refresh()->status);
        $this->assertSame($actor->id, $history->changed_by_user_id);
        $this->assertSame($actor->role, $history->actor_role);
        $this->assertSame($action === 'reject' ? 'Motivo real' : null, $history->reason);
        $this->assertSame($history->reason, $reservation->rejection_reason);
        $this->assertTrue($history->created_at->equalTo(now()));
        $this->patchJson($this->url($action, $reservation), ['rejection_reason' => 'Outra tentativa'])->assertUnprocessable();
        $this->assertDatabaseCount('reservation_status_histories', 1);
    }

    public static function transitions(): array
    {
        return ReservationLifecycleTest::transitions();
    }

    #[DataProvider('atomicOperations')]
    public function test_history_insert_failure_rolls_back_entire_operation(string $action): void
    {
        $resident = User::factory()->morador()->create();
        $admin = User::factory()->admin()->create();
        $area = CommonArea::factory()->create();
        $reservation = $action === 'create' ? null : Reservation::factory()->create($this->period() + ['status' => ReservationStatus::Pending, 'user_id' => $resident->id]);
        $original = $reservation?->refresh()->getRawOriginal();
        $baseline = DB::transactionLevel();
        $attempted = false;
        ReservationStatusHistory::creating(function () use (&$attempted): void {
            $attempted = true;
            throw new RuntimeException('History insert failure');
        });
        try {
            $service = app(ReservationService::class);
            match ($action) {
                'create' => $service->create($resident, $this->data($area)),
                'approve' => $service->approve($admin, $reservation),
                'reject' => $service->reject($admin, $reservation, 'Motivo'),
                'adminCancel' => $service->cancelByAdmin($admin, $reservation),
                'residentCancel' => $service->cancelByResident($resident, $reservation),
            };
            $this->fail('Expected insert failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('History insert failure', $exception->getMessage());
        } finally {
            ReservationStatusHistory::flushEventListeners();
        }
        $this->assertTrue($attempted);
        $this->assertSame($baseline, DB::transactionLevel());
        $this->assertDatabaseCount('reservation_status_histories', 0);
        $this->assertDatabaseCount('reservations', $action === 'create' ? 0 : 1);
        if ($reservation !== null) {
            $this->assertSame($original, $reservation->refresh()->getRawOriginal());
        }
    }

    public static function atomicOperations(): array
    {
        return [['create'], ['approve'], ['reject'], ['adminCancel'], ['residentCancel']];
    }

    public function test_history_is_appended_in_transaction_and_previous_events_stay_unchanged(): void
    {
        $resident = User::factory()->morador()->create();
        $admin = User::factory()->admin()->create();
        $area = CommonArea::factory()->create();
        $baseline = DB::transactionLevel();
        $inserts = [];
        DB::listen(function (QueryExecuted $query) use (&$inserts): void {
            if (str_starts_with($query->sql, 'insert into "reservation_status_histories"')) {
                $inserts[] = $query->connection->transactionLevel();
            }
        });
        $service = app(ReservationService::class);
        $reservation = $service->create($resident, $this->data($area));
        $first = $reservation->statusHistory()->sole()->getRawOriginal();
        $service->approve($admin, $reservation);
        $service->cancelByResident($resident, $reservation);
        $history = $reservation->statusHistory()->get();
        $this->assertSame($first, $history[0]->getRawOriginal());
        $this->assertSame([ReservationStatus::Pending, ReservationStatus::Approved, ReservationStatus::Cancelled], $history->pluck('to_status')->all());
        $this->assertSame([null, ReservationStatus::Pending, ReservationStatus::Approved], $history->pluck('from_status')->all());
        $this->assertCount(3, $inserts);
        foreach ($inserts as $level) {
            $this->assertGreaterThan($baseline, $level);
        }
        $this->assertSame($baseline, DB::transactionLevel());
    }

    public function test_cross_user_and_invalid_actor_attempts_do_not_create_history(): void
    {
        $reservation = Reservation::factory()->create($this->period() + ['status' => ReservationStatus::Pending]);
        $other = User::factory()->morador()->create(['unit_id' => $reservation->unit_id]);
        $this->actingAs($other)->patchJson(route('morador.reservations.cancel', $reservation))->assertForbidden();
        $this->actingAs(User::factory()->porteiro()->create())->patchJson(route('admin.reservations.approve', $reservation))->assertForbidden();
        $this->assertDatabaseCount('reservation_status_histories', 0);
        $this->assertSame(ReservationStatus::Pending, $reservation->refresh()->status);
    }

    public function test_role_is_a_snapshot_and_legacy_reservation_only_gets_actual_new_transition(): void
    {
        $reservation = Reservation::factory()->create($this->period() + ['status' => ReservationStatus::Pending]);
        $admin = User::factory()->admin()->create();
        app(ReservationService::class)->approve($admin, $reservation);
        User::whereKey($admin->id)->update(['role' => UserRole::Morador]);
        $history = ReservationStatusHistory::sole();
        $this->assertSame(UserRole::Admin, $history->actor_role);
        $this->assertSame(ReservationStatus::Pending, $history->from_status);
        $this->assertDatabaseCount('reservation_status_histories', 1);
    }

    public function test_foreign_keys_preserve_reservation_and_actor_references(): void
    {
        $history = ReservationStatusHistory::factory()->create();
        foreach ([$history->reservation, $history->changedBy] as $model) {
            try {
                DB::transaction(fn () => $model->delete());
                $this->fail('Historical references must be preserved.');
            } catch (QueryException) {
                $this->assertDatabaseHas($model->getTable(), ['id' => $model->id]);
            }
        }
        $this->assertModelExists($history);
    }

    private function period(): array
    {
        return ['starts_at' => '2026-10-01 14:00', 'ends_at' => '2026-10-01 16:00'];
    }

    private function data(CommonArea $area): array
    {
        return $this->period() + ['common_area_id' => $area->id];
    }

    private function url(string $action, Reservation $reservation): string
    {
        return route(match ($action) {
            'approve' => 'admin.reservations.approve', 'reject' => 'admin.reservations.reject',
            'adminCancel' => 'admin.reservations.cancel', 'residentCancel' => 'morador.reservations.cancel',
        }, $reservation);
    }
}
