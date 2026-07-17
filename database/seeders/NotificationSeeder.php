<?php

namespace Database\Seeders;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->limit(5)->get();

        foreach ($users as $user) {
            Notification::create([
                'recipient_id' => $user->id,
                'title' => 'Reserva criada',
                'message' => 'Sua solicitação de reserva foi registrada no sistema.',
                'type' => NotificationType::Reservation,
                'sent_at' => now(),
                'is_read' => false,
            ]);

            Notification::create([
                'recipient_id' => $user->id,
                'title' => 'Encomenda recebida',
                'message' => 'Uma encomenda destinada à sua unidade foi recebida na portaria.',
                'type' => NotificationType::Package,
                'sent_at' => now(),
                'is_read' => false,
            ]);
        }
    }
}
