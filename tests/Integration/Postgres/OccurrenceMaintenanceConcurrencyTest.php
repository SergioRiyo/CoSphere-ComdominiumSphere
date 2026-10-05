<?php

namespace Tests\Integration\Postgres;

use App\Enums\IncidentStatus;
use App\Enums\MaintenanceRequestStatus;
use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OccurrenceMaintenanceConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'pgsql'
            || config('database.connections.pgsql.host') !== '127.0.0.1'
            || (string) config('database.connections.pgsql.port') !== '55434'
            || config('database.connections.pgsql.database') !== 'cosphere_m4_test') {
            $this->markTestSkipped('Requires the dedicated local M4 PostgreSQL test database.');
        }
        $this->assertSame('testing', app()->environment());
        $this->assertSame('pgsql', DB::getDriverName());
        $this->assertSame('127.0.0.1', config('database.connections.pgsql.host'));
        $this->assertSame('55434', (string) config('database.connections.pgsql.port'));
        $this->assertSame('cosphere_m4_test', DB::scalar('SELECT current_database()'));
        $this->assertSame(0, DB::transactionLevel());
        $this->artisan('migrate:fresh', ['--no-interaction' => true])->assertSuccessful();
    }

    #[DataProvider('competingTransitions')]
    public function test_competing_transitions_are_serialized_with_one_real_history(string $entity, string $state, array $targets): void
    {
        $admin = User::factory()->admin()->create();
        $target = $entity === 'incident'
            ? Incident::factory()->{$state}()->create()
            : MaintenanceRequest::factory()->{$state}()->create();
        $from = $target->status;
        $provider = ServiceProvider::factory()->create();
        $operations = array_map(fn (string $status): array => [
            'entity' => $entity, 'id' => $target->id, 'actor' => $admin->id, 'status' => $status,
            'data' => ['scheduled_at' => now()->addHour()->toDateTimeString(), 'service_provider_id' => $provider->id, 'cost' => 100],
        ], $targets);
        $results = $this->race($target, $operations);
        sort($results);
        $this->assertSame(['changed', 'invalid'], $results);
        $history = $target->statusHistory()->sole();
        $this->assertSame($from, $history->from_status);
        $this->assertSame($target->refresh()->status, $history->to_status);
        $this->assertSame($admin->id, $history->changed_by_user_id);
        $this->assertSame(0, DB::transactionLevel());
    }

    public static function competingTransitions(): array
    {
        return [
            'duplicate incident' => ['incident', 'open', [IncidentStatus::InProgress->value, IncidentStatus::InProgress->value]],
            'terminal incident' => ['incident', 'inProgress', [IncidentStatus::Completed->value, IncidentStatus::Canceled->value]],
            'duplicate maintenance' => ['maintenance', 'pendingWithoutProvider', [MaintenanceRequestStatus::Scheduled->value, MaintenanceRequestStatus::Scheduled->value]],
            'terminal maintenance' => ['maintenance', 'inProgress', [MaintenanceRequestStatus::Completed->value, MaintenanceRequestStatus::Canceled->value]],
        ];
    }

    /** @param list<array<string, mixed>> $operations
     * @return list<string>
     */
    private function race(Model $target, array $operations): array
    {
        $connection = config('database.connections.pgsql');
        $environment = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => '127.0.0.1', 'DB_PORT' => '55434', 'DB_DATABASE' => 'cosphere_m4_test',
            'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => '', 'DB_SCHEMA' => 'public',
            'DB_SSLMODE' => 'disable', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        ];
        $worker = <<<'PHP'
require $argv[1];
$app = require dirname($argv[1], 4).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$operation = json_decode(base64_decode($argv[2]), true, flags: JSON_THROW_ON_ERROR);
$actor = \App\Models\User::findOrFail($operation['actor']);
$entity = $operation['entity'] === 'incident'
    ? \App\Models\Incident::findOrFail($operation['id'])
    : \App\Models\MaintenanceRequest::findOrFail($operation['id']);
\Illuminate\Support\Facades\DB::statement("SET lock_timeout = '15s'");
echo 'READY '.\Illuminate\Support\Facades\DB::scalar('SELECT pg_backend_pid()').PHP_EOL;
flush();
try {
    if ($operation['entity'] === 'incident') {
        app(\App\Services\IncidentService::class)->transition($actor, $entity, \App\Enums\IncidentStatus::from($operation['status']));
    } else {
        app(\App\Services\MaintenanceRequestService::class)->transition($actor, $entity, \App\Enums\MaintenanceRequestStatus::from($operation['status']), $operation['data']);
    }
    echo 'RESULT changed'.PHP_EOL;
} catch (\Illuminate\Validation\ValidationException) {
    echo 'RESULT invalid'.PHP_EOL;
}
PHP;
        $processes = [];
        $released = false;
        DB::beginTransaction();
        try {
            $target->newQuery()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            foreach ($operations as $operation) {
                $process = new Process([
                    PHP_BINARY, '-r', $worker, __DIR__.'/m4-bootstrap.php',
                    base64_encode(json_encode($operation, JSON_THROW_ON_ERROR)),
                ], base_path(), $environment, timeout: 25);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 12;
            $waiting = 0;
            while (microtime(true) < $deadline) {
                $pids = [];
                foreach ($processes as $process) {
                    if (preg_match('/READY (\\d+)/', $process->getOutput(), $matches)) {
                        $pids[] = (int) $matches[1];
                    }
                    if (! $process->isRunning()) {
                        $this->fail('Worker ended before lock contention: '.$process->getOutput().$process->getErrorOutput());
                    }
                }
                if (count($pids) === 2) {
                    $waiting = (int) DB::scalar("SELECT count(*) FROM pg_stat_activity WHERE pid IN (?, ?) AND wait_event_type = 'Lock'", $pids);
                    if ($waiting === 2) {
                        break;
                    }
                }
                usleep(20000);
            }
            $this->assertSame(2, $waiting, 'Both workers must overlap while waiting for a PostgreSQL lock.');
            DB::commit();
            $released = true;
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
                $this->assertSame(1, preg_match('/RESULT (changed|invalid)/', $process->getOutput(), $matches), $process->getOutput());
                $results[] = $matches[1];
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
}
