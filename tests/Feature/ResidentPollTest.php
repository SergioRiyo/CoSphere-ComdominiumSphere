<?php

namespace Tests\Feature;

use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\User;
use App\Services\PollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResidentPollTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_open_and_closed_polls_separately_without_drafts_future_or_voter_data(): void
    {
        $resident = User::factory()->morador()->create();
        $draft = Poll::factory()->create();
        $future = Poll::factory()->create(['published_at' => now(), 'starts_at' => now()->addDay()]);
        $closed = Poll::factory()->closed()->create();
        Poll::factory()->open()->count(15)->create(['starts_at' => now()->subHour()]);
        $open = Poll::factory()->open()->create(['question' => 'Participar?', 'starts_at' => now()]);
        $option = PollOption::factory()->create(['poll_id' => $open->id]);
        PollVote::factory()->create(['poll_id' => $open->id, 'poll_option_id' => $option->id, 'user_id' => $resident->id]);

        $this->actingAs($resident)->get(route('morador.polls.index'))
            ->assertInertia(fn (Assert $page) => $page->component('morador/polls/index')->where('state', 'open')
                ->where('polls.total', 16)->has('polls.data', 15)->where('polls.data.0.id', $open->id)
                ->where('polls.data.0.has_voted', true)->missing('polls.data.0.votes')->missing('polls.data.0.user_id'));
        $this->get(route('morador.polls.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->where('polls.current_page', 2)->has('polls.data', 1));
        $this->get(route('morador.polls.index', ['state' => 'closed']))
            ->assertInertia(fn (Assert $page) => $page->where('state', 'closed')->where('polls.total', 1)
                ->where('polls.data.0.id', $closed->id)->where('polls.data.0.has_voted', false));
        $this->get(route('morador.polls.show', $draft))->assertNotFound();
        $this->post(route('morador.polls.vote', $draft), ['option_id' => $option->id])->assertNotFound();
        $this->get(route('morador.polls.show', $future))
            ->assertInertia(fn (Assert $page) => $page->where('poll.status', 'scheduled'));
    }

    public function test_voting_uses_session_identity_rejects_duplicates_and_preserves_confirmation_after_closing(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $other = User::factory()->morador()->create();
        $service = app(PollService::class);
        $poll = $service->save($admin, ['question' => 'Participar?', 'options' => ['Sim', 'Não', 'Abster-se']]);
        $service->publish($admin, $poll);
        $this->actingAs($resident)->get(route('morador.polls.show', $poll))
            ->assertInertia(fn (Assert $page) => $page->component('morador/polls/show')->where('poll.status', 'open')
                ->where('poll.has_voted', false)->has('poll.options', 3)->where('poll.options.2.text', 'Abster-se')
                ->missing('results')->missing('poll.votes')->missing('poll.user_id')->missing('poll.options.0.votes'));
        $this->post(route('morador.polls.vote', $poll), ['option_id' => $poll->options[2]->id, 'user_id' => $other->id])
            ->assertRedirect(route('morador.polls.show', $poll));
        $this->assertDatabaseHas('poll_votes', ['poll_id' => $poll->id, 'user_id' => $resident->id, 'poll_option_id' => $poll->options[2]->id]);
        $this->assertDatabaseMissing('poll_votes', ['poll_id' => $poll->id, 'user_id' => $other->id]);
        $this->from(route('morador.polls.show', $poll))->post(route('morador.polls.vote', $poll), ['option_id' => $poll->options[0]->id])
            ->assertRedirect(route('morador.polls.show', $poll))->assertSessionHasErrors('poll');
        $this->assertDatabaseCount('poll_votes', 1);
        $this->get(route('morador.polls.show', $poll))
            ->assertInertia(fn (Assert $page) => $page->where('poll.has_voted', true)->missing('poll.selected_option')->missing('results'));
        $service->close($admin, $poll);
        $this->get(route('morador.polls.show', $poll))
            ->assertInertia(fn (Assert $page) => $page->where('poll.status', 'closed')->where('poll.has_voted', true)->missing('results'));
        $this->actingAs($other)->from(route('morador.polls.show', $poll))->post(route('morador.polls.vote', $poll), ['option_id' => $poll->options[0]->id])
            ->assertRedirect()->assertSessionHasErrors('poll');
        $this->assertDatabaseCount('poll_votes', 1);
    }

    public function test_invalid_and_other_poll_options_cannot_be_voted(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $service = app(PollService::class);
        $poll = $service->save($admin, ['question' => 'Pergunta?', 'options' => ['Sim', 'Não']]);
        $other = $service->save($admin, ['question' => 'Outra?', 'options' => ['A', 'B']]);
        $service->publish($admin, $poll);
        $this->actingAs($resident)->from(route('morador.polls.show', $poll))
            ->post(route('morador.polls.vote', $poll), ['option_id' => $other->options[0]->id])->assertSessionHasErrors('option');
        foreach ([[], ['option_id' => 'abc'], ['option_id' => 0]] as $data) {
            $this->post(route('morador.polls.vote', $poll), $data)->assertSessionHasErrors('option_id');
        }
        $this->assertDatabaseCount('poll_votes', 0);
    }

    public function test_poll_routes_require_active_verified_resident(): void
    {
        $poll = Poll::factory()->open()->create();
        $option = PollOption::factory()->create(['poll_id' => $poll->id]);
        $urls = [route('morador.polls.index'), route('morador.polls.show', $poll)];
        foreach ($urls as $url) {
            auth()->logout();
            $this->get($url)->assertRedirect(route('login'));
            foreach ([User::factory()->admin()->create(), User::factory()->porteiro()->create()] as $user) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
            $this->actingAs(User::factory()->morador()->inactive()->create())->get($url)->assertRedirect(route('login'));
            $this->actingAs(User::factory()->morador()->unverified()->create())->get($url)->assertRedirect(route('verification.notice'));
        }
        auth()->logout();
        $this->post(route('morador.polls.vote', $poll), ['option_id' => $option->id])->assertRedirect(route('login'));
        foreach ([User::factory()->admin()->create(), User::factory()->porteiro()->create()] as $user) {
            $this->actingAs($user)->post(route('morador.polls.vote', $poll), ['option_id' => $option->id])->assertForbidden();
        }
        $this->actingAs(User::factory()->morador()->inactive()->create())->post(route('morador.polls.vote', $poll), ['option_id' => $option->id])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->morador()->unverified()->create())->post(route('morador.polls.vote', $poll), ['option_id' => $option->id])->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('poll_votes', 0);
    }
}
