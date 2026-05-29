<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

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
