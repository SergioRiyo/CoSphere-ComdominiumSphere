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
        Schema::create('visitor_accesses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('visitor_authorization_id')
            ->constrained('visitor_authorizations')
            ->restrictOnDelete();

            $table->foreignId('doorman_id')
            ->constrained('users')
            ->restrictOnDelete();

            $table->dateTime('entry_time')->nullable();
            $table->dateTime('exit_time')->nullable();

            $table->enum('validation_status', [
                'pending',
                'validated',
                'rejected',
            ])->default('pending');

            $table->text('observations')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_accesses');
    }
};
