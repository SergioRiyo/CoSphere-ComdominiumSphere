<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('unit_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('resident_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('title');
            $table->string('category');
            $table->text('description');

            $table->timestamp('opened_at')->useCurrent();

            $table->enum('status', [
                'open',
                'in_progress',
                'completed',
                'canceled',
            ])->default('open');

            $table->enum('priority', [
                'low',
                'medium',
                'high',
            ])->default('medium');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['unit_id', 'status']);
            $table->index(['resident_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
