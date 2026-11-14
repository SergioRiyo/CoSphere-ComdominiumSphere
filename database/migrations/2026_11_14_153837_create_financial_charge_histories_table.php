<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_charge_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_charge_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamps();
            $table->index(['financial_charge_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_charge_histories');
    }
};
