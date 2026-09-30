<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\ReservationStatus;
use App\Models\CommonArea;
use App\Models\CommonAreaBlock;
use App\Models\Notification;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;
use App\Models\User;
use App\Services\CommonAreaBlockService;
use App\Services\NotificationService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ReservationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-30 10:00:00'));
    }

    #[DataProvider('approvalModes')]
    public function test_creation_notifies_exactly_once_for_actual_initial_status(bool $manual): void
    {
        $resident = User::factory()->morador()->create();
        $other = User::factory()->morador()->create(['unit_id' => $resident->unit_id]);
        $area = CommonArea::factory()->create(['requires_approval' => $manual]);
        $this->actingAs($resident)->postJson(route('morador.reservations.store'), $this->data($area) + [
            'recipient_id' => $other->id, 'notification_user_id' => $other->id, 'user_id' => $other->id,
            'status' => $manual ? 'confirmed' : 'pending', 'message' => 'Forjada', 'is_read' => true,
        ])->assertCreated();
        $reservation = Reservation::sole();
        $expected = $manual ? ReservationStatus::Pending : ReservationStatus::Approved;
        $this->assertSame($expected, $reservation->status);
        $this->assertSame($expected, $reservation->statusHistory()->sole()->to_status);
        $notification = $this->assertNotification($reservation, $manual ? 'Solicitação de reserva registrada' : 'Reserva confirmada');
        $this->assertStringContainsString($manual ? 'aguarda análise' : 'confirmada automaticamente', $notification->message);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(0, $other->notifications()->count());
    }

    public static function approvalModes(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('transitions')]
    public function test_transitions_notify_only_on_valid_administrative_events(string $action, ReservationStatus $from, bool $valid, ReservationStatus $target): void
    {
        $reservation = Reservation::factory()->create($this->period() + ['status' => $from]);
        $owner = $reservation->user;
        $admin = User::factory()->admin()->create();
        $other = User::factory()->morador()->create(['unit_id' => $owner->unit_id]);
        $actor = $action === 'residentCancel' ? $owner : $admin;
        $original = $reservation->refresh()->getRawOriginal();
        $this->actingAs($actor);
        $response = $this->patchJson($this->url($action, $reservation), [
            'recipient_id' => $other->id, 'notification_user_id' => $admin->id, 'user_id' => $other->id,
            'rejection_reason' => '  Horário reservado para outra atividade.  ', 'message' => 'Forjada',
        ]);
        if (! $valid) {
            $response->assertUnprocessable();
            $this->assertSame($original, $reservation->refresh()->getRawOriginal());
            $this->assertDatabaseCount('notifications', 0);
            $this->assertDatabaseCount('reservation_status_histories', 0);

            return;
        }
        $response->assertOk();
        $this->assertSame($target, $reservation->refresh()->status);
        $this->assertDatabaseCount('reservation_status_histories', 1);
        if ($action === 'residentCancel') {
            $this->assertDatabaseCount('notifications', 0);
        } else {
            $notification = $this->assertNotification($reservation, match ($action) {
                'approve' => 'Reserva aprovada', 'reject' => 'Reserva recusada', 'adminCancel' => 'Reserva cancelada',
            });
            if ($action === 'reject') {
                $this->assertStringContainsString('Motivo: Horário reservado para outra atividade.', $notification->message);
            }
            if ($action === 'adminCancel') {
                $this->assertStringContainsString('pela administração', $notification->message);
            }
            foreach ([$admin->name, $admin->email, $other->name, $other->email] as $private) {
                $this->assertStringNotContainsString($private, $notification->message);
            }
            $this->assertSame(0, $admin->notifications()->count());
            $this->assertSame(0, $other->notifications()->count());
        }
        $this->patchJson($this->url($action, $reservation), ['rejection_reason' => 'Outra tentativa'])->assertUnprocessable();
        $this->assertDatabaseCount('notifications', $action === 'residentCancel' ? 0 : 1);
        $this->assertDatabaseCount('reservation_status_histories', 1);
    }

    public static function transitions(): array
    {
        return ReservationLifecycleTest::transitions();
    }

    #[DataProvider('notifiedOperations')]
    public function test_notification_persistence_failure_rolls_back_reservation_and_history(string $action): void
    {
        $owner = User::factory()->morador()->create();
        $admin = User::factory()->admin()->create();
        $area = CommonArea::factory()->create(['requires_approval' => $action !== 'autoCreate']);
        $reservation = in_array($action, ['create', 'autoCreate']) ? null
            : Reservation::factory()->create($this->period() + ['user_id' => $owner->id, 'status' => ReservationStatus::Pending]);
        $original = $reservation?->refresh()->getRawOriginal();
        $baseline = DB::transactionLevel();
        $attempted = false;
        Notification::created(function () use (&$attempted): void {
            $attempted = true;
            throw new RuntimeException('Notification persistence failed');
        });
        try {
            $this->operate($action, $owner, $admin, $area, $reservation);
            $this->fail('Expected notification failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Notification persistence failed', $exception->getMessage());
        } finally {
            Notification::flushEventListeners();
        }
        $this->assertTrue($attempted);
        $this->assertSame($baseline, DB::transactionLevel());
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('reservation_status_histories', 0);
        $this->assertDatabaseCount('reservations', $reservation === null ? 0 : 1);
        if ($reservation !== null) {
            $this->assertSame($original, $reservation->refresh()->getRawOriginal());
        }
    }

    public static function notifiedOperations(): array
    {
        return [['create'], ['autoCreate'], ['approve'], ['reject'], ['adminCancel']];
    }

    public function test_existing_notification_service_is_used_and_its_failure_is_not_swallowed(): void
    {
        $owner = User::factory()->morador()->create();
        $area = CommonArea::factory()->create();
        $this->mock(NotificationService::class, function (MockInterface $mock) use ($owner): void {
            $mock->shouldReceive('create')->once()->withArgs(fn (int $id, string $title, string $message, NotificationType $type): bool => $id === $owner->id && $type === NotificationType::Reservation && $title !== '' && $message !== '')
                ->andThrow(new RuntimeException('Service failure'));
        });
        try {
            app(ReservationService::class)->create($owner, $this->data($area));
            $this->fail('Expected service failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Service failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('reservation_status_histories', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_history_failure_never_reaches_notification_creation(): void
    {
        $owner = User::factory()->morador()->create();
        $area = CommonArea::factory()->create();
        $this->mock(NotificationService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('create'));
        ReservationStatusHistory::creating(fn () => throw new RuntimeException('History failure'));
        try {
            app(ReservationService::class)->create($owner, $this->data($area));
            $this->fail('Expected history failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('History failure', $exception->getMessage());
        } finally {
            ReservationStatusHistory::flushEventListeners();
        }
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    #[DataProvider('firstActions')]
    public function test_stale_repeated_or_competing_decisions_do_not_duplicate_notifications(string $first, string $second): void
    {
        $reservation = Reservation::factory()->create($this->period() + ['status' => ReservationStatus::Pending]);
        $stale = Reservation::findOrFail($reservation->id);
        $admin = User::factory()->admin()->create();
        $service = app(ReservationService::class);
        $first === 'approve' ? $service->approve($admin, $reservation) : $service->reject($admin, $reservation, 'Motivo');
        try {
            $second === 'approve' ? $service->approve($admin, $stale) : $service->reject($admin, $stale, 'Outra tentativa');
            $this->fail('Expected stale transition rejection.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('notifications', 1);
            $this->assertDatabaseCount('reservation_status_histories', 1);
        }
        $this->assertSame($first === 'approve' ? ReservationStatus::Approved : ReservationStatus::Rejected, $reservation->refresh()->status);
    }

    public static function firstActions(): array
    {
        return [['approve', 'approve'], ['approve', 'reject'], ['reject', 'approve'], ['reject', 'reject']];
    }

    public function test_notification_uses_reloaded_reservation_owner_instead_of_stale_relationship(): void
    {
        $reservation = Reservation::factory()->create($this->period() + ['status' => ReservationStatus::Pending]);
        $oldOwner = $reservation->user;
        $newOwner = User::factory()->morador()->create(['unit_id' => $oldOwner->unit_id]);
        Reservation::whereKey($reservation->id)->update(['user_id' => $newOwner->id]);
        app(ReservationService::class)->approve(User::factory()->admin()->create(), $reservation);
        $this->assertSame($newOwner->id, Notification::sole()->recipient_id);
        $this->assertSame(0, $oldOwner->notifications()->count());
    }

    #[DataProvider('blockingStatuses')]
    public function test_blocks_do_not_cancel_or_notify_but_explicit_admin_cancel_notifies_once(ReservationStatus $status): void
    {
        $reservation = Reservation::factory()->create($this->period() + ['status' => $status]);
        $admin = User::factory()->admin()->create();
        $data = $this->data($reservation->commonArea) + ['reason' => 'Manutenção'];
        $blocks = app(CommonAreaBlockService::class);
        try {
            $blocks->create($admin, $data);
            $this->fail('Expected block conflict.');
        } catch (ValidationException) {
            $this->assertSame($status, $reservation->refresh()->status);
            $this->assertDatabaseCount('notifications', 0);
            $this->assertDatabaseCount('reservation_status_histories', 0);
            $this->assertDatabaseCount('common_area_blocks', 0);
        }
        app(ReservationService::class)->cancelByAdmin($admin, $reservation);
        $block = $blocks->create($admin, $data);
        $blocks->remove($admin, $block);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertNotification($reservation->refresh(), 'Reserva cancelada');
    }

    public static function blockingStatuses(): array
    {
        return [[ReservationStatus::Pending], [ReservationStatus::Approved]];
    }

    public function test_conflicting_creation_blocked_approval_and_cross_user_action_do_not_notify(): void
    {
        $owner = User::factory()->morador()->create();
        $area = CommonArea::factory()->create();
        $pending = Reservation::factory()->create($this->data($area) + ['status' => ReservationStatus::Pending]);
        $original = $pending->refresh()->getRawOriginal();
        $this->actingAs($owner)->postJson(route('morador.reservations.store'), $this->data($area))->assertUnprocessable();
        CommonAreaBlock::factory()->create($this->data($area));
        $this->actingAs(User::factory()->admin()->create())->patchJson(route('admin.reservations.approve', $pending))->assertUnprocessable();
        $this->actingAs($owner)->patchJson(route('morador.reservations.cancel', $pending))->assertForbidden();
        $this->assertSame($original, $pending->refresh()->getRawOriginal());
        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('reservation_status_histories', 0);
    }

    public function test_generated_notification_appears_in_existing_inbox_and_cannot_be_read_by_co_resident(): void
    {
        $owner = User::factory()->morador()->create();
        $other = User::factory()->morador()->create(['unit_id' => $owner->unit_id]);
        $reservation = app(ReservationService::class)->create($owner, $this->data(CommonArea::factory()->create()));
        $notification = Notification::sole();
        $this->actingAs($owner)->get(route('morador.notifications.index'))->assertInertia(fn (Assert $page) => $page
            ->component('morador/notifications/index')->has('notifications.data', 1)
            ->where('notifications.data.0.title', $notification->title)->where('notifications.data.0.message', $notification->message)
            ->where('notifications.data.0.type_label', 'Reserva')->where('notifications.data.0.is_read', false));
        $this->actingAs($other)->get(route('morador.notifications.index', ['recipient_id' => $owner->id]))->assertInertia(fn (Assert $page) => $page->has('notifications.data', 0));
        $this->patch(route('morador.notifications.read', $notification))->assertNotFound();
        $this->assertFalse($notification->refresh()->is_read);
        $this->actingAs($owner)->patch(route('morador.notifications.read', $notification))->assertRedirect();
        $this->assertTrue($notification->refresh()->is_read);
        $this->assertSame(ReservationStatus::Pending, $reservation->refresh()->status);
        $this->assertDatabaseCount('reservation_status_histories', 1);
    }

    public function test_notification_is_inserted_after_history_in_transaction_and_outer_rollback_removes_everything(): void
    {
        $owner = User::factory()->morador()->create();
        $area = CommonArea::factory()->create();
        $baseline = DB::transactionLevel();
        $inserts = [];
        DB::listen(function (QueryExecuted $query) use (&$inserts): void {
            if (str_starts_with($query->sql, 'insert into') && (str_contains($query->sql, 'reservations') || str_contains($query->sql, 'reservation_status_histories') || str_contains($query->sql, 'notifications'))) {
                $inserts[] = [$query->sql, $query->connection->transactionLevel()];
            }
        });
        try {
            DB::transaction(function () use ($owner, $area): void {
                app(ReservationService::class)->create($owner, $this->data($area));
                $this->assertDatabaseCount('notifications', 1);
                throw new RuntimeException('Outer rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Outer rollback', $exception->getMessage());
        }
        $this->assertCount(3, $inserts);
        $this->assertStringContainsString('reservations', $inserts[0][0]);
        $this->assertStringContainsString('reservation_status_histories', $inserts[1][0]);
        $this->assertStringContainsString('notifications', $inserts[2][0]);
        foreach ($inserts as [, $level]) {
            $this->assertGreaterThan($baseline, $level);
        }
        $this->assertSame($baseline, DB::transactionLevel());
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('reservation_status_histories', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    private function assertNotification(Reservation $reservation, string $title): Notification
    {
        $notification = Notification::sole();
        $this->assertSame($reservation->user_id, $notification->recipient_id);
        $this->assertSame(NotificationType::Reservation, $notification->type);
        $this->assertSame($title, $notification->title);
        $this->assertFalse($notification->is_read);
        $this->assertTrue($notification->sent_at->equalTo(now()));
        foreach ([$reservation->commonArea->name, '01/10/2026', '14:00:15', '16:00:30'] as $context) {
            $this->assertStringContainsString($context, $notification->message);
        }

        return $notification;
    }

    private function period(): array
    {
        return ['starts_at' => '2026-10-01 14:00:15', 'ends_at' => '2026-10-01 16:00:30'];
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

    private function operate(string $action, User $owner, User $admin, CommonArea $area, ?Reservation $reservation): void
    {
        $service = app(ReservationService::class);
        match ($action) {
            'create', 'autoCreate' => $service->create($owner, $this->data($area)),
            'approve' => $service->approve($admin, $reservation),
            'reject' => $service->reject($admin, $reservation, 'Motivo'),
            'adminCancel' => $service->cancelByAdmin($admin, $reservation),
        };
    }
}
