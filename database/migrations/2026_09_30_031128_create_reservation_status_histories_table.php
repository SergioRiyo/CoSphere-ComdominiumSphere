<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->restrictOnDelete();
            $table->enum('from_status', ['pending', 'confirmed', 'rejected', 'cancelled', 'completed'])->nullable();
            $table->enum('to_status', ['pending', 'confirmed', 'rejected', 'cancelled', 'completed']);
            $table->foreignId('changed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('actor_role', ['admin', 'morador', 'porteiro']);
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at');
            $table->index(['reservation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_status_histories');
    }
};
