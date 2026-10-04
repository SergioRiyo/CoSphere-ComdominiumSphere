<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function paginateForUser(User $user): LengthAwarePaginator
    {
        return $user->notifications()
            ->orderByRaw('sent_at IS NULL')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->through(static fn (Notification $notification): array => [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => $notification->type->value,
                'type_label' => $notification->type->label(),
                'sent_at' => $notification->sent_at?->toISOString(),
                'is_read' => $notification->is_read,
            ]);
    }

    public function markAsRead(User $user, int $notificationId): void
    {
        $notification = $user->notifications()->findOrFail($notificationId);

        if (! $notification->is_read) {
            $notification->update(['is_read' => true]);
        }
    }

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
