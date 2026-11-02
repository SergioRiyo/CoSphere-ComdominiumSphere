<?php

namespace Tests\Feature;

use App\Enums\PollStatus;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\User;
use App\Services\PollQueryService;
use App\Services\PollService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;
use Throwable;

class PollDomainTest extends TestCase
{
    use RefreshDatabase;

    private function assertRejected(string $exceptionClass, callable $operation): void
    {
        try {
            $operation();
            $this->fail('A operação deveria ter sido rejeitada.');
        } catch (Throwable $exception) {
            $this->assertInstanceOf($exceptionClass, $exception);
        }
    }

    public function test_admin_creates_edits_and_publishes_immutable_poll_using_server_identity_and_dates(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $service = app(PollService::class);
        $this->assertRejected(ValidationException::class, fn () => $service->save($admin, ['question' => 'Pergunta?', 'options' => ['Sim']]));
        $this->assertDatabaseCount('polls', 0);
        $poll = $service->save($admin, [
            'question' => 'Pergunta?', 'options' => ['Sim', 'Não'],
            'author_id' => $other->id, 'published_at' => '2000-01-01', 'starts_at' => '2000-01-01', 'ends_at' => '2000-01-01',
        ]);
        $this->assertSame($admin->id, $poll->author_id);
        $this->assertSame(PollStatus::Draft, $poll->status());
        $this->assertNull($poll->published_at);
        $poll = $service->save($admin, ['question' => 'Nova pergunta?', 'options' => ['A', 'B', 'Abstenção'], 'author_id' => $other->id], $poll);
        $this->assertSame(['A', 'B', 'Abstenção'], $poll->options->pluck('text')->all());
        $this->assertSame($admin->id, $poll->author_id);
        $poll = $service->publish($admin, $poll);
        $this->assertSame(PollStatus::Open, $poll->status());
        $this->assertTrue($poll->published_at->equalTo($poll->starts_at));
        $this->assertNull($poll->ends_at);
        $publishedAt = $poll->published_at;
        $this->travel(1)->hour();
        $this->assertTrue($service->publish($admin, $poll)->published_at->equalTo($publishedAt));
        $this->assertRejected(AuthorizationException::class, fn () => $service->save($admin, ['question' => 'Alterada', 'options' => ['X', 'Y']], $poll));
        $this->assertSame(['A', 'B', 'Abstenção'], $poll->fresh()->options->pluck('text')->all());
        $closed = $service->close($admin, $poll);
        $this->assertSame(PollStatus::Closed, $closed->status());
        $this->assertTrue($service->close($admin, $poll)->ends_at->equalTo($closed->ends_at));
    }

    public function test_publication_revalidates_minimum_options_and_draft_cannot_be_closed(): void
    {
        $admin = User::factory()->admin()->create();
        $poll = Poll::factory()->create();
        PollOption::factory()->create(['poll_id' => $poll->id]);
        $service = app(PollService::class);
        $this->assertRejected(ValidationException::class, fn () => $service->publish($admin, $poll));
        $this->assertRejected(ValidationException::class, fn () => $service->close($admin, $poll));
        $this->assertNull($poll->fresh()->published_at);
    }

    public function test_only_verified_active_admin_can_manage_and_only_verified_active_resident_can_vote(): void
    {
        $service = app(PollService::class);
        $admin = User::factory()->admin()->create();
        $poll = $service->save($admin, ['question' => 'Pergunta?', 'options' => ['Sim', 'Não']]);
        $this->assertRejected(AuthorizationException::class, fn () => $service->vote(User::factory()->morador()->create(), $poll, $poll->options[0]->id));
        foreach ([User::factory()->morador()->create(), User::factory()->porteiro()->create(), User::factory()->admin()->inactive()->create(), User::factory()->admin()->unverified()->create()] as $user) {
            $this->assertRejected(AuthorizationException::class, fn () => $service->save($user, ['question' => 'P?', 'options' => ['A', 'B']]));
            $this->assertRejected(AuthorizationException::class, fn () => $service->publish($user, $poll));
        }
        $service->publish($admin, $poll);
        foreach ([$admin, User::factory()->porteiro()->create(), User::factory()->morador()->inactive()->create(), User::factory()->morador()->unverified()->create()] as $user) {
            $this->assertRejected(AuthorizationException::class, fn () => $service->vote($user, $poll, $poll->options[0]->id));
        }
        $this->assertDatabaseCount('poll_votes', 0);
    }

    public function test_duplicate_cross_poll_and_votes_outside_the_open_window_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $service = app(PollService::class);
        $poll = $service->save($admin, ['question' => 'Pergunta?', 'options' => ['Sim', 'Não']]);
        $other = $service->save($admin, ['question' => 'Outra?', 'options' => ['Um', 'Dois']]);
        $service->publish($admin, $poll);
        $this->assertRejected(ValidationException::class, fn () => $service->vote($resident, $poll, $other->options[0]->id));
        $vote = $service->vote($resident, $poll, $poll->options[0]->id);
        $this->assertSame($resident->id, $vote->user_id);
        $this->assertRejected(ValidationException::class, fn () => $service->vote($resident, $poll, $poll->options[1]->id));
        $this->assertRejected(LogicException::class, fn () => $vote->update(['poll_option_id' => $poll->options[1]->id]));
        $this->assertRejected(LogicException::class, fn () => $vote->delete());
        $this->assertSame($poll->options[0]->id, $vote->fresh()->poll_option_id);
        $future = Poll::factory()->create(['published_at' => now(), 'starts_at' => now()->addHour()]);
        $futureOption = PollOption::factory()->create(['poll_id' => $future->id]);
        $this->assertSame(PollStatus::Scheduled, $future->status());
        $this->assertRejected(ValidationException::class, fn () => $service->vote($resident, $future, $futureOption->id));
        $service->close($admin, $poll);
        $this->assertRejected(ValidationException::class, fn () => $service->vote(User::factory()->morador()->create(), $poll, $poll->options[0]->id));
        $this->assertDatabaseCount('poll_votes', 1);
    }

    public function test_database_enforces_unique_voter_and_option_belonging_to_poll(): void
    {
        $poll = Poll::factory()->open()->create();
        $other = Poll::factory()->open()->create();
        $option = PollOption::factory()->create(['poll_id' => $poll->id]);
        $otherOption = PollOption::factory()->create(['poll_id' => $other->id]);
        $resident = User::factory()->morador()->create();
        PollVote::factory()->create(['poll_id' => $poll->id, 'poll_option_id' => $option->id, 'user_id' => $resident->id]);
        $this->assertRejected(QueryException::class, fn () => DB::transaction(fn () => PollVote::factory()->create(['poll_id' => $poll->id, 'poll_option_id' => $option->id, 'user_id' => $resident->id])));
        $this->assertRejected(QueryException::class, fn () => DB::transaction(fn () => PollVote::factory()->create(['poll_id' => $poll->id, 'poll_option_id' => $otherOption->id])));
        $this->assertDatabaseCount('poll_votes', 1);
    }

    public function test_results_are_aggregated_without_voter_identity_and_available_only_to_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(PollService::class);
        $query = app(PollQueryService::class);
        $poll = $service->save($admin, ['question' => 'Pergunta?', 'options' => ['Sim', 'Não', 'Abstenção']]);
        $service->publish($admin, $poll);
        $empty = $query->results($admin, $poll);
        $this->assertSame(0, $empty['total']);
        $this->assertSame(0.0, $empty['options'][0]['percentage']);
        $resident = User::factory()->morador()->create();
        $service->vote($resident, $poll, $poll->options[0]->id);
        $service->vote(User::factory()->morador()->create(), $poll, $poll->options[0]->id);
        $service->vote(User::factory()->morador()->create(), $poll, $poll->options[1]->id);
        $results = $query->results($admin, $poll);
        $this->assertSame(3, $results['total']);
        $this->assertSame([2, 1, 0], array_column($results['options'], 'votes'));
        $this->assertSame([66.67, 33.33, 0.0], array_column($results['options'], 'percentage'));
        $this->assertSame(['id', 'text', 'votes', 'percentage'], array_keys($results['options'][0]));
        $this->assertTrue($query->hasVoted($resident, $poll));
        $this->assertFalse($query->hasVoted(User::factory()->morador()->create(), $poll));
        $this->assertRejected(AuthorizationException::class, fn () => $query->results($resident, $poll));
        $this->assertRejected(AuthorizationException::class, fn () => $query->results(User::factory()->porteiro()->create(), $poll));
        $service->close($admin, $poll);
        $this->assertSame($results, $query->results($admin, $poll));
    }

    public function test_resident_queries_hide_drafts_and_allow_closed_polls(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $draft = Poll::factory()->create();
        $open = Poll::factory()->open()->create();
        $closed = Poll::factory()->closed()->create();
        $query = app(PollQueryService::class);
        $this->assertSame(3, $query->paginate($admin)->total());
        $this->assertSame(2, $query->paginate($resident)->total());
        $this->assertSame($open->id, $query->find($resident, $open->id)->id);
        $this->assertSame($closed->id, $query->find($resident, $closed->id)->id);
        $this->assertRejected(ModelNotFoundException::class, fn () => $query->find($resident, $draft->id));
        $this->assertRejected(AuthorizationException::class, fn () => $query->paginate(User::factory()->porteiro()->create()));
    }
}
