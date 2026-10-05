<?php

namespace Tests\Integration\Module3;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\CommonArea;
use App\Models\CommonAreaBlock;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class Module3ConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', app()->environment());
        $this->assertSame('pgsql', DB::getDriverName());
        $this->assertSame('127.0.0.1', config('database.connections.pgsql.host'));
        $this->assertSame('cosphere_m3_test', DB::scalar('SELECT current_database()'));
        $this->assertSame(0, DB::transactionLevel());
        $this->artisan('migrate:fresh', ['--no-interaction' => true])->assertSuccessful();
    }

    public function test_simultaneous_reservations_cannot_both_claim_the_same_period(): void
    {
        $area = CommonArea::factory()->create(['requires_approval' => false]);
        $first = User::factory()->morador()->create();
        $second = User::factory()->morador()->create();
        $results = $this->race($area, [
            $this->operation($first, 'POST', 'morador.reservations.store', data: $this->period($area)),
            $this->operation($second, 'POST', 'morador.reservations.store', data: $this->period($area)),
        ]);
        $this->assertStatuses($results, [201, 422]);
        $winner = $results[0]['status'] === 201 ? $first : $second;
        $reservation = Reservation::sole();
        $this->assertSame($winner->id, $reservation->user_id);
        $this->assertSame($winner->unit_id, $reservation->unit_id);
        $this->assertSame(ReservationStatus::Approved, $reservation->status);
        $this->assertSame($winner->id, $reservation->statusHistory()->sole()->changed_by_user_id);
        $this->assertSame($winner->id, Notification::sole()->recipient_id);
        $this->assertSame(0, CommonAreaBlock::count());
    }

    public function test_reservation_and_block_revalidate_after_the_same_area_lock(): void
    {
        $area = CommonArea::factory()->create();
        $resident = User::factory()->morador()->create();
        $admin = User::factory()->admin()->create();
        $results = $this->race($area, [
            $this->operation($resident, 'POST', 'morador.reservations.store', data: $this->period($area)),
            $this->operation($admin, 'POST', 'admin.common-area-blocks.store', data: $this->period($area) + ['reason' => 'Manutenção concorrente']),
        ]);
        $this->assertStatuses($results, [201, 422]);
        $reservationWon = $results[0]['status'] === 201;
        $this->assertDatabaseCount('reservations', $reservationWon ? 1 : 0);
        $this->assertDatabaseCount('common_area_blocks', $reservationWon ? 0 : 1);
        $this->assertDatabaseCount('reservation_status_histories', $reservationWon ? 1 : 0);
        $this->assertDatabaseCount('notifications', $reservationWon ? 1 : 0);
        if ($reservationWon) {
            $this->assertSame(ReservationStatus::Pending, Reservation::sole()->status);
        } else {
            $this->assertSame($admin->id, CommonAreaBlock::sole()->admin_id);
        }
    }

    public function test_approve_and_reject_produce_one_transition_and_one_notification(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Pending,
            'starts_at' => '2026-10-01 14:00:00', 'ends_at' => '2026-10-01 16:00:00',
        ]);
        $approveActor = User::factory()->admin()->create();
        $rejectActor = User::factory()->admin()->create();
        $results = $this->race($reservation, [
            $this->operation($approveActor, 'PATCH', 'admin.reservations.approve', $reservation),
            $this->operation($rejectActor, 'PATCH', 'admin.reservations.reject', $reservation, ['rejection_reason' => 'Recusa concorrente']),
        ]);
        $this->assertStatuses($results, [200, 422]);
        $approved = $results[0]['status'] === 200;
        $winner = $approved ? $approveActor : $rejectActor;
        $status = $approved ? ReservationStatus::Approved : ReservationStatus::Rejected;
        $this->assertSame($status, $reservation->refresh()->status);
        $history = $reservation->statusHistory()->sole();
        $this->assertSame(ReservationStatus::Pending, $history->from_status);
        $this->assertSame($status, $history->to_status);
        $this->assertSame($winner->id, $history->changed_by_user_id);
        $this->assertSame($approved ? null : 'Recusa concorrente', $history->reason);
        $notification = Notification::sole();
        $this->assertSame($reservation->user_id, $notification->recipient_id);
        $this->assertSame($approved ? 'Reserva aprovada' : 'Reserva recusada', $notification->title);
    }

    public function test_doorman_and_housemate_pickup_preserve_the_first_confirmation(): void
    {
        $resident = User::factory()->morador()->create();
        $housemate = User::factory()->morador()->create(['unit_id' => $resident->unit_id]);
        $doorman = User::factory()->porteiro()->create();
        $order = Order::factory()->create([
            'resident_id' => $resident->id, 'unit_id' => $resident->unit_id,
            'status' => OrderStatus::ReceivedAtGate,
            'received_by_id' => $doorman->id, 'received_at' => '2026-09-30 09:00:00',
            'picked_up_by_id' => null, 'picked_up_at' => null,
        ]);
        $results = $this->race($order, [
            $this->operation($doorman, 'PATCH', 'portaria.orders.pickup', $order),
            $this->operation($housemate, 'PATCH', 'morador.orders.pickup', $order),
        ]);
        $this->assertStatuses($results, [302, 422]);
        $winner = $results[0]['status'] === 302 ? $doorman : $housemate;
        $this->assertSame(OrderStatus::PickedUp, $order->refresh()->status);
        $this->assertSame($winner->id, $order->picked_up_by_id);
        $this->assertSame('2026-09-30 10:00:00', $order->picked_up_at->format('Y-m-d H:i:s'));
        $this->assertSame($doorman->id, $order->received_by_id);
        $this->assertSame('2026-09-30 09:00:00', $order->received_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('notifications', 0);
    }

    /**
     * Hold the target row until both independent HTTP workers are waiting for a
     * PostgreSQL lock. The requests then finish in their own transactions.
     *
     * @param  list<array<string, mixed>>  $operations
     * @return list<array{status: int, body: mixed}>
     */
    private function race(Model $target, array $operations): array
    {
        $connection = config('database.connections.pgsql');
        $environment = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
            'DB_DATABASE' => $connection['database'], 'DB_USERNAME' => $connection['username'],
            'DB_PASSWORD' => $connection['password'] ?? '', 'DB_SSLMODE' => 'disable',
            'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        ];
        $processes = [];
        $released = false;
        DB::beginTransaction();
        try {
            $target->newQuery()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            foreach ($operations as $operation) {
                $process = new Process([PHP_BINARY, __DIR__.'/http-worker.php', base64_encode(json_encode($operation, JSON_THROW_ON_ERROR))], base_path(), $environment, timeout: 25);
                $process->start();
                $processes[] = $process;
            }

            $deadline = microtime(true) + 12;
            $waiting = 0;
            while (microtime(true) < $deadline) {
                $pids = [];
                foreach ($processes as $process) {
                    if (preg_match('/READY (\d+)/', $process->getOutput(), $matches)) {
                        $pids[] = (int) $matches[1];
                    }
                    if (! $process->isRunning()) {
                        $this->fail('Worker ended before lock contention: '.$process->getOutput().$process->getErrorOutput());
                    }
                }
                if (count($pids) === 2) {
                    $waiting = (int) DB::scalar(
                        "SELECT count(*) FROM pg_stat_activity WHERE pid IN (?, ?) AND wait_event_type = 'Lock'",
                        $pids
                    );
                    if ($waiting === 2) {
                        break;
                    }
                }
                usleep(20000);
            }
            $this->assertSame(2, $waiting, 'Both HTTP workers must demonstrably overlap while waiting in PostgreSQL.');
            $this->assertNotSame($pids[0], $pids[1]);
            DB::commit();
            $released = true;
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $this->assertSame(1, preg_match('/RESULT (.+)/', $process->getOutput(), $matches), $process->getOutput());
                $results[] = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            if (! $released && DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }

    /** @param list<array{status: int, body: mixed}> $results
     * @param  list<int>  $expected
     */
    private function assertStatuses(array $results, array $expected): void
    {
        $actual = array_column($results, 'status');
        sort($actual);
        sort($expected);
        $this->assertSame($expected, $actual, json_encode($results));
    }

    /** @return array<string, mixed> */
    private function operation(User $actor, string $method, string $route, ?Model $target = null, array $data = []): array
    {
        return ['actor' => $actor->id, 'method' => $method, 'url' => route($route, $target, false), 'data' => $data];
    }

    /** @return array<string, mixed> */
    private function period(CommonArea $area): array
    {
        return ['common_area_id' => $area->id, 'starts_at' => '2026-10-01 14:00:00', 'ends_at' => '2026-10-01 16:00:00'];
    }
}
