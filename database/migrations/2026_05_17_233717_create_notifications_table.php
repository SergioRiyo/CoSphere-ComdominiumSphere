<?php

use App\Enums\NotificationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('recipient_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('title');
            $table->text('message');

            $table->enum('type', [
                NotificationType::Reservation->value,
                NotificationType::Visitor->value,
                NotificationType::Package->value,
                NotificationType::Occurrence->value,
                NotificationType::System->value,
            ]);

            $table->timestamp('send_at')->nullable();
            $table->boolean('is_read')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['recipient_id', 'is_read']);
            $table->index('type');
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
