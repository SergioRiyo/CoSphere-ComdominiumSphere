<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lost_found_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lost_found_conversation_id')->constrained()->restrictOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['lost_found_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_found_messages');
    }
};
