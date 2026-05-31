<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('common_area_id')->constrained()->restrictOnDelete(); // area reservada
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); // Pessoa que fez
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->enum('status', [
                'pending',
                'confirmed',
                'cancelled',
                'rejected',
                'completed',
            ])->default('confirmed');
            $table->string('rejection_reason')->nullable();

            $table->timestamps();

            // acelera consultas frequentes p relatorio tambem
            $table->index(['common_area_id', 'starts_at', 'ends_at']);
            $table->index(['user_id', 'starts_at']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
