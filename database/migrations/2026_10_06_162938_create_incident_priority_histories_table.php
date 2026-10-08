<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_priority_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_id')->constrained()->restrictOnDelete();
            $table->enum('from_priority', ['low', 'medium', 'high']);
            $table->enum('to_priority', ['low', 'medium', 'high']);
            $table->foreignId('changed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('actor_role', ['admin', 'morador', 'porteiro']);
            $table->timestamp('created_at');
            $table->index(['incident_id', 'created_at', 'id'], 'incident_priority_history_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_priority_histories');
    }
};
