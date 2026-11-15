<?php

namespace Tests\Feature;

use App\Jobs\SendAnnouncementEmail;
use App\Mail\AnnouncementPublishedMail;
use App\Models\Announcement;
use App\Models\Notification;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Services\PollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AnnouncementPollNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_an_announcement_queues_one_email_and_notification_per_eligible_resident(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $other = User::factory()->morador()->create();
        $inactive = User::factory()->morador()->inactive()->create();
        $unverified = User::factory()->morador()->unverified()->create();
        $announcement = Announcement::factory()->create();
        $service = app(AnnouncementService::class);

        $this->assertDatabaseCount('jobs', 0);
        $service->publish($admin, $announcement);
        $service->publish($admin, $announcement);

        $this->assertSame(2, DB::table('jobs')->where('queue', 'announcement-email')->count());
        $this->assertSame(2, Notification::where('announcement_id', $announcement->id)->count());
        foreach ([$resident, $other] as $eligible) {
            $this->assertDatabaseHas('notifications', ['announcement_id' => $announcement->id, 'recipient_id' => $eligible->id]);
        }
        foreach ([$admin, $inactive, $unverified] as $ineligible) {
            $this->assertDatabaseMissing('notifications', ['announcement_id' => $announcement->id, 'recipient_id' => $ineligible->id]);
        }
    }

    public function test_announcements_are_sent_with_escaped_content_and_only_to_eligible_residents(): void
    {
        $resident = User::factory()->morador()->create();
        $inactive = User::factory()->morador()->inactive()->create();
        $draft = Announcement::factory()->create();
        $published = Announcement::factory()->published()->create(['title' => 'Aviso importante', 'content' => 'Conteúdo <script>alert(1)</script>']);
        Mail::fake();

        (new SendAnnouncementEmail($published->id, $resident->id))->handle();
        (new SendAnnouncementEmail($published->id, $inactive->id))->handle();
        (new SendAnnouncementEmail($draft->id, $resident->id))->handle();

        Mail::assertSent(AnnouncementPublishedMail::class, 1);
        Mail::assertSent(AnnouncementPublishedMail::class, fn (AnnouncementPublishedMail $mail): bool => $mail->hasTo($resident->email));
        $html = (new AnnouncementPublishedMail($published))->render();
        $this->assertStringContainsString('Aviso importante', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString(route('morador.announcements.show', $published->id), $html);
    }

    public function test_announcement_email_job_is_processed_from_its_dedicated_queue(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $announcement = Announcement::factory()->create();
        Mail::fake();

        app(AnnouncementService::class)->publish($admin, $announcement);

        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'announcement-email', '--once' => true])
            ->assertExitCode(0);
        Mail::assertSent(AnnouncementPublishedMail::class, fn (AnnouncementPublishedMail $mail): bool => $mail->hasTo($resident->email));
        $this->assertSame(0, DB::table('jobs')->where('queue', 'announcement-email')->count());
    }

    public function test_rolling_back_a_publication_also_discards_the_notification_and_email_job(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->morador()->create();
        $announcement = Announcement::factory()->create();

        try {
            DB::transaction(function () use ($admin, $announcement): void {
                app(AnnouncementService::class)->publish($admin, $announcement);
                throw new \RuntimeException('Rollback de teste');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Rollback de teste', $exception->getMessage());
        }

        $this->assertNull($announcement->fresh()->published_at);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_publishing_a_poll_notifies_eligible_residents_once_and_not_drafts(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $other = User::factory()->morador()->create();
        $inactive = User::factory()->morador()->inactive()->create();
        $unverified = User::factory()->morador()->unverified()->create();
        $poll = Poll::factory()->create(['question' => 'Escolha da data']);
        PollOption::factory()->count(2)->create(['poll_id' => $poll->id]);
        $this->assertDatabaseCount('notifications', 0);

        $service = app(PollService::class);
        $service->publish($admin, $poll);
        $service->publish($admin, $poll);

        $this->assertSame(2, Notification::count());
        foreach ([$resident, $other] as $eligible) {
            $this->assertDatabaseHas('notifications', ['recipient_id' => $eligible->id, 'title' => 'Nova enquete disponível', 'message' => 'Escolha da data']);
        }
        foreach ([$admin, $inactive, $unverified] as $ineligible) {
            $this->assertDatabaseMissing('notifications', ['recipient_id' => $ineligible->id, 'title' => 'Nova enquete disponível']);
        }
    }
}
