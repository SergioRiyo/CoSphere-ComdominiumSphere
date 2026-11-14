<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lost_found_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lost_found_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('resident_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['lost_found_item_id', 'resident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_found_conversations');
    }
};
