<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_request_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('changed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_role');
            $table->json('changes');
            $table->timestamp('created_at');
            $table->index(['maintenance_request_id', 'created_at', 'id'], 'maintenance_changes_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_request_changes');
    }
};
