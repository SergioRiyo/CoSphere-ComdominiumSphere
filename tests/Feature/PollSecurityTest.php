<?php

namespace Tests\Feature;

use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\User;
use App\Services\PollQueryService;
use App\Services\PollService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PollSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_injected_identity_and_foreign_option_do_not_change_ownership_or_results(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $victim = User::factory()->morador()->create();
        $service = app(PollService::class);
        $poll = $service->save($admin, ['question' => 'Pergunta?', 'options' => ['Sim', 'Não']]);
        $other = $service->save($admin, ['question' => 'Outra?', 'options' => ['A', 'B']]);
        $service->publish($admin, $poll);
        $this->actingAs($resident)->from(route('morador.polls.show', $poll))
            ->post(route('morador.polls.vote', $poll), ['option_id' => $other->options[0]->id, 'user_id' => $victim->id])
            ->assertSessionHasErrors('option');
        $this->assertDatabaseCount('poll_votes', 0);
        $this->post(route('morador.polls.vote', $poll), ['option_id' => $poll->options[1]->id, 'user_id' => $victim->id])
            ->assertRedirect(route('morador.polls.show', $poll));
        $this->assertDatabaseHas('poll_votes', ['poll_id' => $poll->id, 'user_id' => $resident->id, 'poll_option_id' => $poll->options[1]->id]);
        $this->assertDatabaseMissing('poll_votes', ['poll_id' => $poll->id, 'user_id' => $victim->id]);
        $results = app(PollQueryService::class)->results($admin, $poll);
        $this->assertSame(1, $results['total']);
        $this->assertSame([0, 1], array_column($results['options'], 'votes'));
        $this->get(route('morador.polls.show', $poll))->assertInertia(fn (Assert $page) => $page
            ->where('poll.has_voted', true)->missing('poll.votes')->missing('poll.user_id')->missing('results'));
    }

    public function test_database_rejects_cross_poll_options_and_duplicate_voters(): void
    {
        $poll = Poll::factory()->open()->create();
        $other = Poll::factory()->open()->create();
        $option = PollOption::factory()->create(['poll_id' => $poll->id]);
        $foreignOption = PollOption::factory()->create(['poll_id' => $other->id]);
        $resident = User::factory()->morador()->create();
        foreach ([
            ['poll_id' => $poll->id, 'poll_option_id' => $foreignOption->id, 'user_id' => $resident->id],
            ['poll_id' => $other->id, 'poll_option_id' => $option->id, 'user_id' => $resident->id],
        ] as $invalid) {
            try {
                DB::transaction(fn () => PollVote::create($invalid));
                $this->fail('The database must reject cross-poll options.');
            } catch (QueryException) {
                $this->assertDatabaseCount('poll_votes', 0);
            }
        }
        PollVote::create(['poll_id' => $poll->id, 'poll_option_id' => $option->id, 'user_id' => $resident->id]);
        try {
            DB::transaction(fn () => PollVote::create(['poll_id' => $poll->id, 'poll_option_id' => $option->id, 'user_id' => $resident->id]));
            $this->fail('The database must reject duplicate votes.');
        } catch (QueryException) {
            $this->assertDatabaseCount('poll_votes', 1);
        }
    }

    public function test_publication_and_period_boundaries_are_enforced_for_http_votes(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 12:00:00'));
        $resident = User::factory()->morador()->create();
        $draft = Poll::factory()->create();
        $futurePublication = Poll::factory()->create(['published_at' => now()->addSecond(), 'starts_at' => now()->addSecond()]);
        $futureStart = Poll::factory()->create(['published_at' => now()->subMinute(), 'starts_at' => now()->addSecond()]);
        $ended = Poll::factory()->create(['published_at' => now()->subHour(), 'starts_at' => now()->subHour(), 'ends_at' => now()]);
        $opening = Poll::factory()->create(['published_at' => now(), 'starts_at' => now(), 'ends_at' => now()->addSecond()]);
        $options = [];
        foreach ([$draft, $futurePublication, $futureStart, $ended, $opening] as $poll) {
            $options[$poll->id] = PollOption::factory()->create(['poll_id' => $poll->id])->id;
        }
        $this->actingAs($resident);
        foreach ([$draft, $futurePublication] as $poll) {
            $this->post(route('morador.polls.vote', $poll), ['option_id' => $options[$poll->id]])->assertNotFound();
        }
        foreach ([$futureStart, $ended] as $poll) {
            $this->from(route('morador.polls.show', $poll))->post(route('morador.polls.vote', $poll), ['option_id' => $options[$poll->id]])
                ->assertSessionHasErrors('poll');
        }
        $this->post(route('morador.polls.vote', $opening), ['option_id' => $options[$opening->id]])->assertRedirect(route('morador.polls.show', $opening));
        $this->assertDatabaseCount('poll_votes', 1);
        $this->assertDatabaseHas('poll_votes', ['poll_id' => $opening->id, 'user_id' => $resident->id, 'poll_option_id' => $options[$opening->id]]);
    }

    public function test_service_rechecks_persisted_role_and_activity_after_request_authentication(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $service = app(PollService::class);
        $poll = $service->save($admin, ['question' => 'Pergunta?', 'options' => ['Sim', 'Não']]);
        $service->publish($admin, $poll);
        $this->actingAs($resident);
        DB::table('users')->where('id', $resident->id)->update(['is_active' => false]);
        $this->post(route('morador.polls.vote', $poll), ['option_id' => $poll->options[0]->id])->assertForbidden();
        $this->assertDatabaseCount('poll_votes', 0);
    }
}
