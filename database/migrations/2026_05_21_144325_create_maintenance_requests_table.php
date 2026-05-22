<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('incident_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('service_provider_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('description');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('executed_at')->nullable();
            $table->decimal('cost', 10, 2)->nullable();

            $table->enum('status', [
                'pending',
                'scheduled',
                'in_progress',
                'completed',
                'canceled',
            ])->default('pending');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['incident_id', 'status']);
            $table->index(['service_provider_id']);
            $table->index(['admin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_requests');
    }
};
