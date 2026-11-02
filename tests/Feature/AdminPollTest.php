<?php

namespace Tests\Feature;

use App\Enums\PollStatus;
use App\Models\Poll;
use App\Models\User;
use App\Services\PollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPollTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_list_paginated_polls_with_states_and_periods(): void
    {
        $admin = User::factory()->admin()->create();
        Poll::factory()->count(15)->create();
        $open = Poll::factory()->open()->create(['author_id' => $admin->id, 'question' => 'Enquete aberta']);
        $this->actingAs($admin)->get(route('admin.polls.index'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/polls')
                ->where('polls.total', 16)->has('polls.data', 15)
                ->where('polls.data.0.id', $open->id)->where('polls.data.0.question', 'Enquete aberta')
                ->where('polls.data.0.author', $admin->name)->where('polls.data.0.status', 'open')
                ->where('polls.data.0.starts_at', $open->starts_at->toISOString())->where('polls.data.0.ends_at', null));
        $this->get(route('admin.polls.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->where('polls.current_page', 2)->has('polls.data', 1));
        $this->get(route('admin.polls.create'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/poll-form')->where('poll', null)->where('results', null));
    }

    public function test_admin_can_create_edit_publish_and_close_poll_without_injecting_author_or_dates(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.polls.store'), [
            'question' => 'Pergunta?', 'options' => ['Sim', 'Não'], 'author_id' => $other->id,
            'published_at' => '2001-01-01', 'starts_at' => '2001-01-01', 'ends_at' => '2001-01-01',
        ])->assertRedirect();
        $poll = Poll::sole();
        $this->assertSame($admin->id, $poll->author_id);
        $this->assertSame(PollStatus::Draft, $poll->status());
        $this->assertNull($poll->starts_at);
        $this->assertNull($poll->ends_at);
        $this->get(route('admin.polls.show', $poll))
            ->assertInertia(fn (Assert $page) => $page->component('admin/poll-form')
                ->where('poll.status', 'draft')->where('poll.question', 'Pergunta?')
                ->has('poll.options', 2)->where('results', null));
        $this->patch(route('admin.polls.update', $poll), ['question' => 'Alterada?', 'options' => ['Sim', 'Não', 'Abster-se']])
            ->assertRedirect(route('admin.polls.show', $poll));
        $this->assertSame(['Sim', 'Não', 'Abster-se'], $poll->fresh()->options->pluck('text')->all());
        $this->patch(route('admin.polls.publish', $poll))->assertRedirect(route('admin.polls.show', $poll));
        $this->assertSame(PollStatus::Open, $poll->fresh()->status());
        $this->patch(route('admin.polls.update', $poll), ['question' => 'Inválida', 'options' => ['Outra', 'Opção']])->assertForbidden();
        $this->assertSame(['Sim', 'Não', 'Abster-se'], $poll->fresh()->options->pluck('text')->all());
        $this->patch(route('admin.polls.close', $poll))->assertRedirect(route('admin.polls.show', $poll));
        $this->assertSame(PollStatus::Closed, $poll->fresh()->status());
        $this->assertNotNull($poll->fresh()->ends_at);
    }

    public function test_form_validation_preserves_input_and_rejects_empty_or_repeated_options(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->from(route('admin.polls.create'))
            ->post(route('admin.polls.store'), ['question' => 'Pergunta preservada', 'options' => ['Sim']])
            ->assertRedirect(route('admin.polls.create'))->assertSessionHasErrors('options')
            ->assertSessionHasInput('question', 'Pergunta preservada')->assertSessionHasInput('options', ['Sim']);
        $this->post(route('admin.polls.store'), ['question' => 'Pergunta', 'options' => ['Sim', 'Sim']])
            ->assertSessionHasErrors(['options.0', 'options.1']);
        $this->post(route('admin.polls.store'), ['question' => '', 'options' => ['Sim', '']])
            ->assertSessionHasErrors(['question', 'options.1']);
        $this->assertDatabaseCount('polls', 0);
    }

    public function test_show_displays_only_aggregate_results_and_correct_percentages(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(PollService::class);
        $poll = $service->save($admin, ['question' => 'Pergunta?', 'options' => ['Sim', 'Não', 'Abster-se']]);
        $service->publish($admin, $poll);
        $service->vote(User::factory()->morador()->create(), $poll, $poll->options[0]->id);
        $service->vote(User::factory()->morador()->create(), $poll, $poll->options[0]->id);
        $service->vote(User::factory()->morador()->create(), $poll, $poll->options[2]->id);
        $this->actingAs($admin)->get(route('admin.polls.show', $poll))
            ->assertInertia(fn (Assert $page) => $page->component('admin/poll-form')->where('poll.status', 'open')
                ->where('results.total', 3)->has('results.options', 3)
                ->where('results.options.0.votes', 2)->where('results.options.0.percentage', 66.67)
                ->where('results.options.1.votes', 0)->where('results.options.2.text', 'Abster-se')
                ->where('results.options.2.votes', 1)->where('results.options.2.percentage', 33.33)
                ->missing('results.options.0.user_id')->missing('results.voters')->missing('poll.votes'));
        $service->close($admin, $poll);
        $this->get(route('admin.polls.show', $poll))
            ->assertInertia(fn (Assert $page) => $page->where('poll.status', 'closed')->where('results.total', 3));
    }

    public function test_management_routes_require_active_verified_admin(): void
    {
        $poll = Poll::factory()->create();
        $getUrls = [route('admin.polls.index'), route('admin.polls.create'), route('admin.polls.show', $poll)];
        foreach ($getUrls as $url) {
            auth()->logout();
            $this->get($url)->assertRedirect(route('login'));
            foreach ([User::factory()->morador()->create(), User::factory()->porteiro()->create()] as $user) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
            $this->actingAs(User::factory()->admin()->inactive()->create())->get($url)->assertRedirect(route('login'));
            $this->actingAs(User::factory()->admin()->unverified()->create())->get($url)->assertRedirect(route('verification.notice'));
        }
        foreach ([User::factory()->morador()->create(), User::factory()->porteiro()->create()] as $user) {
            $this->actingAs($user)->post(route('admin.polls.store'), ['question' => 'Pergunta', 'options' => ['A', 'B']])->assertForbidden();
            $this->patch(route('admin.polls.update', $poll), ['question' => 'Pergunta', 'options' => ['A', 'B']])->assertForbidden();
            $this->patch(route('admin.polls.publish', $poll))->assertForbidden();
            $this->patch(route('admin.polls.close', $poll))->assertForbidden();
        }
        $this->assertNull($poll->fresh()->published_at);
    }
}
