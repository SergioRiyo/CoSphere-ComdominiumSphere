<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lost_found_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 10);
            $table->string('description', 1000);
            $table->string('condition', 255);
            $table->string('location', 255);
            $table->date('event_date');
            $table->string('status', 20)->default('open');
            $table->string('photo_path')->nullable();
            $table->string('photo_mime', 30)->nullable();
            $table->timestamps();
            $table->index(['status', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_found_items');
    }
};
