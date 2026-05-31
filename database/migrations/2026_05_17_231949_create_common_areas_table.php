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
        Schema::create('common_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->time('available_from')->nullable(); // disponível de
            $table->time('available_until')->nullable(); // disponível até
            $table->timestamps();
            $table->unsignedSmallInteger('max_reservation_minutes');
            $table->text('rules'); // regras da área
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_approval')->default(true); // aprovaçao da área
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('common_areas');
    }
};
