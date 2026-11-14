<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lost_found_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lost_found_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 30);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();
            $table->index(['lost_found_item_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_found_histories');
    }
};
