<?php

namespace Tests\Integration\Postgres;

use App\Models\PollVote;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Services\PollQueryService;
use App\Services\PollService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PollConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', app()->environment());
        $this->assertSame('pgsql', DB::getDriverName());
        $this->assertSame('127.0.0.1', config('database.connections.pgsql.host'));
        $this->assertSame('55435', (string) config('database.connections.pgsql.port'));
        $this->assertSame('cosphere_m5_test', DB::scalar('SELECT current_database()'));
        $this->assertSame('cosphere_m5_test', DB::scalar('SELECT current_user'));
        $this->assertSame(0, DB::transactionLevel());
        $this->artisan('migrate:fresh', ['--no-interaction' => true])->assertSuccessful();
    }

    public function test_simultaneous_announcement_publications_deliver_once_per_eligible_resident(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $residents = User::factory()->morador()->count(2)->create();
        $inactive = User::factory()->morador()->inactive()->create();
        $unverified = User::factory()->morador()->unverified()->create();
        $announcement = app(AnnouncementService::class)->save($admin, ['title' => 'Comunicado concorrente', 'content' => 'Conteúdo']);
        $url = route('admin.announcements.publish', $announcement, false);

        $results = $this->race($announcement, [
            ['actor' => $admin->id, 'method' => 'PATCH', 'url' => $url, 'data' => []],
            ['actor' => $otherAdmin->id, 'method' => 'PATCH', 'url' => $url, 'data' => []],
        ]);

        $this->assertSame([302, 302], array_column($results, 'status'));
        $this->assertNotNull($announcement->fresh()->published_at);
        $this->assertSame(2, $announcement->notifications()->count());
        foreach ($residents as $resident) {
            $this->assertSame(1, $announcement->notifications()->where('recipient_id', $resident->id)->count());
        }
        foreach ([$admin, $otherAdmin, $inactive, $unverified] as $ineligible) {
            $this->assertSame(0, $announcement->notifications()->where('recipient_id', $ineligible->id)->count());
        }
    }

    public function test_two_simultaneous_http_votes_from_same_resident_produce_one_vote(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $other = User::factory()->morador()->create();
        $service = app(PollService::class);
        $poll = $service->save($admin, ['question' => 'Escolha uma opção', 'options' => ['Sim', 'Não']]);
        $service->publish($admin, $poll);
        $url = route('morador.polls.vote', $poll, false);
        $operations = [
            ['actor' => $resident->id, 'method' => 'POST', 'url' => $url, 'data' => ['option_id' => $poll->options[0]->id, 'user_id' => $other->id]],
            ['actor' => $resident->id, 'method' => 'POST', 'url' => $url, 'data' => ['option_id' => $poll->options[1]->id, 'user_id' => $other->id]],
        ];
        $results = $this->race($resident, $operations);
        $statuses = array_column($results, 'status');
        $sorted = $statuses;
        sort($sorted);
        $this->assertSame([302, 422], $sorted);
        $winningOption = $statuses[0] === 302 ? $poll->options[0]->id : $poll->options[1]->id;
        $vote = PollVote::sole();
        $this->assertSame($poll->id, $vote->poll_id);
        $this->assertSame($resident->id, $vote->user_id);
        $this->assertSame($winningOption, $vote->poll_option_id);
        $this->assertSame($poll->id, $vote->option->poll_id);
        $this->assertDatabaseMissing('poll_votes', ['poll_id' => $poll->id, 'user_id' => $other->id]);
        $aggregate = app(PollQueryService::class)->results($admin, $poll);
        $this->assertSame(1, $aggregate['total']);
        $this->assertSame(1, array_sum(array_column($aggregate['options'], 'votes')));
        $this->assertSame($winningOption, collect($aggregate['options'])->firstWhere('votes', 1)['id']);

        try {
            DB::transaction(fn () => PollVote::create(['poll_id' => $poll->id, 'poll_option_id' => $winningOption, 'user_id' => $resident->id]));
            $this->fail('The PostgreSQL unique constraint must reject duplicate votes.');
        } catch (QueryException) {
            $this->assertSame(1, PollVote::count());
        }
    }

    public function test_vote_and_close_race_are_serialized_and_results_match_committed_vote(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $service = app(PollService::class);
        $poll = $service->save($admin, ['question' => 'Pergunta?', 'options' => ['Sim', 'Não']]);
        $service->publish($admin, $poll);
        $results = $this->race($poll, [
            ['actor' => $resident->id, 'method' => 'POST', 'url' => route('morador.polls.vote', $poll, false), 'data' => ['option_id' => $poll->options[0]->id]],
            ['actor' => $admin->id, 'method' => 'PATCH', 'url' => route('admin.polls.close', $poll, false), 'data' => []],
        ]);
        $this->assertContains($results[0]['status'], [302, 422]);
        $this->assertSame(302, $results[1]['status']);
        $this->assertNotNull($poll->fresh()->ends_at);
        $expected = $results[0]['status'] === 302 ? 1 : 0;
        $this->assertSame($expected, PollVote::count());
        $aggregate = app(PollQueryService::class)->results($admin, $poll);
        $this->assertSame($expected, $aggregate['total']);
        $this->assertSame($expected, $aggregate['options'][0]['votes']);
        $this->assertSame(0, $aggregate['options'][1]['votes']);
    }

    /** @param list<array<string, mixed>> $operations
     * @return list<array{status: int, body: mixed}>
     */
    private function race(Model $target, array $operations): array
    {
        $connection = config('database.connections.pgsql');
        $environment = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => '127.0.0.1', 'DB_PORT' => '55435', 'DB_DATABASE' => 'cosphere_m5_test',
            'DB_USERNAME' => 'cosphere_m5_test', 'DB_PASSWORD' => $connection['password'], 'DB_SCHEMA' => 'public',
            'DB_SSLMODE' => 'disable', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        ];
        $processes = [];
        $released = false;
        DB::beginTransaction();
        try {
            $target->newQuery()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            foreach ($operations as $operation) {
                $process = new Process([PHP_BINARY, __DIR__.'/m5-http-worker.php', base64_encode(json_encode($operation, JSON_THROW_ON_ERROR))], base_path(), $environment, timeout: 25);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 12;
            $waiting = 0;
            $pids = [];
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
                    $waiting = (int) DB::scalar("SELECT count(*) FROM pg_stat_activity WHERE pid IN (?, ?) AND wait_event_type = 'Lock'", $pids);
                    if ($waiting === 2) {
                        break;
                    }
                }
                usleep(20000);
            }
            $this->assertSame(2, $waiting, 'Both HTTP requests must be waiting simultaneously on PostgreSQL locks.');
            $this->assertNotSame($pids[0], $pids[1]);
            DB::commit();
            $released = true;
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
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
}
