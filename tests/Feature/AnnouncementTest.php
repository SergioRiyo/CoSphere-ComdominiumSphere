<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Notification;
use App\Models\User;
use App\Services\AnnouncementQueryService;
use App\Services\AnnouncementService;
use App\Services\NotificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_and_updates_drafts_without_trusting_author_or_publication_date(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Novo comunicado', 'content' => 'Conteúdo original', 'author_id' => $other->id, 'published_at' => '2020-01-01',
        ])->assertRedirect();
        $announcement = Announcement::sole();
        $this->assertSame($admin->id, $announcement->author_id);
        $this->assertNull($announcement->published_at);
        $this->assertSame(0, Notification::count());

        $this->patch(route('admin.announcements.update', $announcement), [
            'title' => 'Alterado', 'content' => 'Novo conteúdo', 'author_id' => $other->id, 'published_at' => '2020-01-01',
        ])->assertRedirect();
        $announcement->refresh();
        $this->assertSame('Alterado', $announcement->title);
        $this->assertSame('Novo conteúdo', $announcement->content);
        $this->assertSame($admin->id, $announcement->author_id);
        $this->assertNull($announcement->published_at);
        $this->post(route('admin.announcements.store'), ['title' => '', 'content' => ''])->assertSessionHasErrors(['title', 'content']);
    }

    public function test_publish_is_idempotent_and_notifies_only_eligible_residents_at_publication(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $second = User::factory()->morador()->create();
        $inactive = User::factory()->morador()->inactive()->create();
        $unverified = User::factory()->morador()->unverified()->create();
        User::factory()->porteiro()->create();
        $announcement = Announcement::factory()->create(['author_id' => $admin->id]);

        $this->actingAs($admin)->patch(route('admin.announcements.publish', $announcement), ['published_at' => '2000-01-01'])->assertRedirect();
        $publishedAt = $announcement->fresh()->published_at;
        $this->assertNotNull($publishedAt);
        $this->assertTrue($publishedAt->isToday());
        $this->assertSame(2, Notification::where('announcement_id', $announcement->id)->count());
        foreach ([$resident, $second] as $recipient) {
            $this->assertDatabaseHas('notifications', ['announcement_id' => $announcement->id, 'recipient_id' => $recipient->id, 'type' => 'announcement']);
        }
        $this->assertDatabaseMissing('notifications', ['announcement_id' => $announcement->id, 'recipient_id' => $inactive->id]);
        $this->assertDatabaseMissing('notifications', ['announcement_id' => $announcement->id, 'recipient_id' => $unverified->id]);
        $late = User::factory()->morador()->create();
        $this->travel(1)->day();
        $this->patch(route('admin.announcements.publish', $announcement))->assertRedirect();
        $this->assertTrue($publishedAt->equalTo($announcement->fresh()->published_at));
        $this->assertSame(2, Notification::where('announcement_id', $announcement->id)->count());
        $this->assertDatabaseMissing('notifications', ['announcement_id' => $announcement->id, 'recipient_id' => $late->id]);
        $this->patch(route('admin.announcements.update', $announcement), ['title' => 'Não', 'content' => 'Não'])->assertForbidden();
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $announcement->fresh()));
        $this->assertFalse(Gate::forUser($admin)->allows('forceDelete', $announcement->fresh()));
    }

    public function test_failed_notification_rolls_back_publication_and_previous_deliveries(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->morador()->count(2)->create();
        $announcement = Announcement::factory()->create();
        $calls = 0;
        $this->mock(NotificationService::class, function (MockInterface $mock) use (&$calls): void {
            $mock->shouldReceive('createForAnnouncement')->twice()->andReturnUsing(function (...$args) use (&$calls) {
                if (++$calls === 2) {
                    throw new RuntimeException('Notification failure');
                }

                return (new NotificationService)->createForAnnouncement(...$args);
            });
        });

        try {
            app(AnnouncementService::class)->publish($admin, $announcement);
            $this->fail('Publication should fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Notification failure', $exception->getMessage());
        }
        $this->assertNull($announcement->fresh()->published_at);
        $this->assertDatabaseCount('notifications', 0);
        $this->app->forgetInstance(NotificationService::class);
        app(AnnouncementService::class)->publish($admin, $announcement);
        $this->assertNotNull($announcement->fresh()->published_at);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_visibility_and_authorization_for_each_role(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $doorman = User::factory()->porteiro()->create();
        $draft = Announcement::factory()->create();
        $published = Announcement::factory()->published()->create();
        $query = app(AnnouncementQueryService::class);
        $this->assertSame(2, $query->paginate($admin)->total());
        $this->assertSame(1, $query->paginate($resident)->total());
        $this->assertSame($published->id, $query->find($resident, $published->id)->id);
        $this->assertFalse(Gate::forUser($resident)->allows('view', $draft));
        try {
            $query->find($resident, $draft->id);
            $this->fail('Resident must not see drafts.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
        foreach ([$resident, $doorman] as $user) {
            $this->actingAs($user)->post(route('admin.announcements.store'), ['title' => 'x', 'content' => 'x'])->assertForbidden();
            $this->patch(route('admin.announcements.update', $draft), ['title' => 'x', 'content' => 'x'])->assertForbidden();
            $this->patch(route('admin.announcements.publish', $draft))->assertForbidden();
        }
        try {
            $query->paginate($doorman);
            $this->fail('Doorman must not see announcements.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
        $this->assertNull($draft->fresh()->published_at);
    }

    public function test_services_recheck_role_activity_and_verification(): void
    {
        $announcement = Announcement::factory()->create();
        $service = app(AnnouncementService::class);
        $users = [User::factory()->admin()->inactive()->create(), User::factory()->admin()->unverified()->create(), User::factory()->morador()->create()];
        foreach ($users as $user) {
            foreach (['save', 'publish'] as $action) {
                try {
                    if ($action === 'save') {
                        $service->save($user, ['title' => 'Não', 'content' => 'Não'], $announcement);
                    } else {
                        $service->publish($user, $announcement);
                    }
                    $this->fail('Unauthorized users must not change announcements.');
                } catch (AuthorizationException) {
                    $this->assertNull($announcement->fresh()->published_at);
                }
            }
        }
    }

    public function test_notification_foreign_key_rejects_orphans_and_prevents_deleting_referenced_announcement(): void
    {
        $announcement = Announcement::factory()->published()->create();
        $notification = Notification::factory()->forAnnouncement($announcement->id)->create();
        try {
            DB::transaction(fn () => DB::table('announcements')->where('id', $announcement->id)->delete());
            $this->fail('Referenced announcements must not be deleted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
        }
        try {
            DB::transaction(fn () => Notification::factory()->forAnnouncement(999999)->create());
            $this->fail('Orphan notifications must not be created.');
        } catch (QueryException) {
            $this->assertDatabaseCount('notifications', 1);
        }
    }

    public function test_published_announcement_cannot_be_deleted_even_without_recipients_and_migration_does_not_discard_data(): void
    {
        $announcement = Announcement::factory()->create();
        Announcement::whereKey($announcement->id)->update(['published_at' => now()]);
        foreach (['delete', 'forceDelete'] as $method) {
            try {
                $announcement->$method();
                $this->fail('Published announcements cannot be deleted.');
            } catch (LogicException) {
                $this->assertDatabaseHas('announcements', ['id' => $announcement->id, 'deleted_at' => null]);
            }
        }
        $migration = require database_path('migrations/2026_10_31_231232_create_announcements_table.php');
        try {
            $migration->down();
            $this->fail('Rollback must not discard announcements.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('announcements exist', $exception->getMessage());
            $this->assertDatabaseHas('announcements', ['id' => $announcement->id]);
        }
    }
}
