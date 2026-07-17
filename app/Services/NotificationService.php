<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Notification;

class NotificationService
{
    public function create(
        int $recipientId,
        string $title,
        string $message,
        NotificationType $type = NotificationType::Visitor
    ): Notification {
        return Notification::create([
            'recipient_id' => $recipientId,
            'title' => $title,
            'message' => $message,
            'type' => $type->value,
            'sent_at' => now(),
            'is_read' => false,
        ]);
    }
}