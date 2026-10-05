<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_id')->constrained()->restrictOnDelete();
            $table->enum('from_status', ['open', 'in_progress', 'completed', 'canceled'])->nullable();
            $table->enum('to_status', ['open', 'in_progress', 'completed', 'canceled']);
            $table->foreignId('changed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('actor_role', ['admin', 'morador', 'porteiro']);
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at');
            $table->index(['incident_id', 'created_at', 'id'], 'incident_history_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_status_histories');
    }
};
