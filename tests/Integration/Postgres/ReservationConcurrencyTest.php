<?php

namespace Tests\Integration\Postgres;

use App\Enums\ReservationStatus;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class ReservationConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    public function test_overlapping_requests_are_serialized_and_only_one_is_created(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('This concurrency test requires PostgreSQL.');
        }

        if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
            $this->markTestSkipped('This concurrency test requires the pcntl and sockets extensions.');
        }

        $area = CommonArea::factory()->create(['requires_approval' => false]);
        $resident = User::factory()->morador()->create();
        $startsAt = now()->addDay()->setTime(10, 0);
        $endsAt = $startsAt->copy()->addHours(2);
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($sockets === false) {
            throw new RuntimeException('Could not create the process synchronization socket.');
        }

        DB::disconnect();

        $processId = pcntl_fork();
        if ($processId === -1) {
            throw new RuntimeException('Could not fork the competing reservation process.');
        }

        if ($processId === 0) {
            fclose($sockets[0]);
            ob_start(static fn (string $output): string => '');
            fread($sockets[1], 2);

            try {
                app(ReservationService::class)->create($resident, [
                    'common_area_id' => $area->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ]);
                fwrite($sockets[1], '|created');
            } catch (ValidationException) {
                fwrite($sockets[1], '|conflict');
            } catch (Throwable $exception) {
                fwrite($sockets[1], '|error:'.$exception::class.':'.$exception->getMessage());
            }

            fclose($sockets[1]);
            exit(0);
        }

        fclose($sockets[1]);
        DB::reconnect();
        DB::beginTransaction();
        CommonArea::query()->lockForUpdate()->findOrFail($area->id);
        fwrite($sockets[0], 'go');
        usleep(200_000);

        Reservation::create([
            'common_area_id' => $area->id,
            'user_id' => $resident->id,
            'unit_id' => $resident->unit_id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => ReservationStatus::Approved,
        ]);
        DB::commit();

        pcntl_waitpid($processId, $status);
        $childResult = stream_get_contents($sockets[0]);
        fclose($sockets[0]);

        $this->assertSame('|conflict', $childResult);
        $this->assertTrue(pcntl_wifexited($status));
        $this->assertSame(0, pcntl_wexitstatus($status));
        $this->assertDatabaseCount('reservations', 1);
    }
}
