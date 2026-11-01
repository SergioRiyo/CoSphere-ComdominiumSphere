<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResidentAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_can_only_list_and_view_published_announcements_with_pagination(): void
    {
        $resident = User::factory()->morador()->create();
        $author = User::factory()->admin()->create();
        $draft = Announcement::factory()->create(['title' => 'Segredo', 'content' => 'Rascunho confidencial']);
        Announcement::factory()->published()->count(15)->create(['published_at' => now()->subDay()]);
        $published = Announcement::factory()->published()->create([
            'author_id' => $author->id, 'title' => 'Comunicado importante', 'content' => "Primeira linha\nSegunda linha", 'published_at' => now(),
        ]);

        $this->actingAs($resident)->get(route('morador.announcements.index'))
            ->assertInertia(fn (Assert $page) => $page->component('morador/announcements/index')
                ->where('announcements.total', 16)->has('announcements.data', 15)
                ->where('announcements.data.0.id', $published->id)
                ->where('announcements.data.0.title', $published->title)
                ->where('announcements.data.0.author', $author->name)
                ->where('announcements.data.0.published_at', $published->published_at->toISOString())
                ->missing('announcements.data.0.content'));
        $this->get(route('morador.announcements.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->where('announcements.current_page', 2)->has('announcements.data', 1));
        $this->get(route('morador.announcements.show', $published))
            ->assertInertia(fn (Assert $page) => $page->component('morador/announcements/show')
                ->where('announcement.id', $published->id)->where('announcement.title', $published->title)
                ->where('announcement.content', "Primeira linha\nSegunda linha")->where('announcement.author', $author->name));
        $this->get(route('morador.announcements.show', $draft))->assertNotFound();
        $this->get(route('morador.announcements.show', 999999))->assertNotFound();
    }

    public function test_resident_routes_require_active_verified_resident(): void
    {
        $published = Announcement::factory()->published()->create();
        $urls = [route('morador.announcements.index'), route('morador.announcements.show', $published)];
        foreach ($urls as $url) {
            auth()->logout();
            $this->get($url)->assertRedirect(route('login'));
            foreach ([User::factory()->admin()->create(), User::factory()->porteiro()->create()] as $user) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
            $this->actingAs(User::factory()->morador()->inactive()->create())->get($url)->assertRedirect(route('login'));
            $this->actingAs(User::factory()->morador()->unverified()->create())->get($url)->assertRedirect(route('verification.notice'));
        }
    }

    public function test_publication_delivers_once_and_remains_available_after_reading_and_to_later_residents(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $other = User::factory()->morador()->create();
        $inactive = User::factory()->morador()->inactive()->create();
        $unverified = User::factory()->morador()->unverified()->create();
        $doorman = User::factory()->porteiro()->create();
        $announcement = Announcement::factory()->create(['title' => 'Aviso', 'content' => 'Conteúdo integral']);

        $this->actingAs($admin)->patch(route('admin.announcements.publish', $announcement))->assertRedirect();
        $this->patch(route('admin.announcements.publish', $announcement))->assertRedirect();
        $this->assertSame(2, Notification::where('announcement_id', $announcement->id)->count());
        foreach ([$resident, $other] as $user) {
            $this->assertDatabaseHas('notifications', ['announcement_id' => $announcement->id, 'recipient_id' => $user->id, 'type' => 'announcement']);
        }
        foreach ([$admin, $inactive, $unverified, $doorman] as $user) {
            $this->assertDatabaseMissing('notifications', ['announcement_id' => $announcement->id, 'recipient_id' => $user->id]);
        }
        $notification = Notification::where('announcement_id', $announcement->id)->where('recipient_id', $resident->id)->sole();
        $this->actingAs($resident)->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page->component('notifications/index')
                ->where('notifications.data.0.id', $notification->id)
                ->where('notifications.data.0.type_label', 'Comunicado')
                ->where('notifications.data.0.announcement_id', $announcement->id));
        $this->from(route('notifications.index'))->patch(route('notifications.read', $notification))->assertRedirect();
        $this->assertTrue($notification->fresh()->is_read);
        $this->get(route('morador.announcements.show', $announcement))
            ->assertInertia(fn (Assert $page) => $page->where('announcement.content', 'Conteúdo integral'));

        $late = User::factory()->morador()->create();
        $unverified->markEmailAsVerified();
        foreach ([$late, $unverified] as $user) {
            $this->actingAs($user)->get(route('morador.announcements.index'))
                ->assertInertia(fn (Assert $page) => $page->where('announcements.total', 1)->where('announcements.data.0.id', $announcement->id));
            $this->get(route('morador.announcements.show', $announcement))
                ->assertInertia(fn (Assert $page) => $page->where('announcement.content', 'Conteúdo integral'));
            $this->assertDatabaseMissing('notifications', ['announcement_id' => $announcement->id, 'recipient_id' => $user->id]);
        }
    }
}
