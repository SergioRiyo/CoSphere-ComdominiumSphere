<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_request_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained()->restrictOnDelete();
            $table->enum('from_status', ['pending', 'scheduled', 'in_progress', 'completed', 'canceled'])->nullable();
            $table->enum('to_status', ['pending', 'scheduled', 'in_progress', 'completed', 'canceled']);
            $table->foreignId('changed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('actor_role', ['admin', 'morador', 'porteiro']);
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at');
            $table->index(['maintenance_request_id', 'created_at', 'id'], 'maintenance_history_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_request_status_histories');
    }
};
