<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_announcement_delivery_is_idempotent_even_after_soft_deletion(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $service = app(NotificationService::class);
        $notification = $service->createForAnnouncement(101, $first->id, 'Comunicado', 'Mensagem original');
        $repeat = $service->createForAnnouncement(101, $first->id, 'Outro título', 'Outra mensagem');

        $this->assertSame($notification->id, $repeat->id);
        $this->assertSame('Mensagem original', $repeat->message);
        $this->assertSame(NotificationType::Announcement, $repeat->type);
        $this->assertNotSame($notification->id, $service->createForAnnouncement(101, $second->id, 'Comunicado', 'Mensagem')->id);
        $this->assertNotSame($notification->id, $service->createForAnnouncement(102, $first->id, 'Comunicado', 'Mensagem')->id);
        $notification->delete();
        $this->assertSame($notification->id, $service->createForAnnouncement(101, $first->id, 'Comunicado', 'Mensagem')->id);
        $this->assertSame(3, Notification::withTrashed()->count());

        try {
            DB::transaction(fn () => Notification::factory()->forAnnouncement(101)->create(['recipient_id' => $first->id]));
            $this->fail('The database must reject duplicate announcement deliveries.');
        } catch (QueryException) {
            $this->assertSame(3, Notification::withTrashed()->count());
        }
    }

    public function test_migration_rollback_preserves_legacy_notifications_and_rejects_announcement_data(): void
    {
        $legacy = Notification::factory()->create(['type' => NotificationType::System]);
        $announcement = Notification::factory()->forAnnouncement(42)->create();
        $migration = require database_path('migrations/2026_10_31_223915_prepare_notifications_for_announcements.php');

        try {
            $migration->down();
            $this->fail('Rollback must not discard announcement data.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('announcement notifications exist', $exception->getMessage());
            $this->assertDatabaseHas('notifications', ['id' => $announcement->id, 'announcement_id' => 42]);
        }

        $announcement->forceDelete();
        $migration->down();
        $this->assertFalse(Schema::hasColumn('notifications', 'announcement_id'));
        $this->assertSame(NotificationType::System, $legacy->fresh()->type);
        $migration->up();
        $this->assertTrue(Schema::hasColumn('notifications', 'announcement_id'));
        $this->assertSame(NotificationType::System, $legacy->fresh()->type);
        $this->assertSame(NotificationType::Announcement, Notification::factory()->forAnnouncement(42)->create()->type);
    }

    public function test_deve_criar_notificacao_para_usuario(): void
    {
        $recipient = User::factory()->create();

        $notification = app(NotificationService::class)->create(
            recipientId: $recipient->id,
            title: 'Aviso importante',
            message: 'Sua notificacao foi registrada.',
            type: NotificationType::System,
        );

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertSame(NotificationType::System, $notification->type);
        $this->assertFalse($notification->is_read);
        $this->assertNotNull($notification->sent_at);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'recipient_id' => $recipient->id,
            'title' => 'Aviso importante',
            'type' => NotificationType::System->value,
            'is_read' => false,
        ]);
    }
}
