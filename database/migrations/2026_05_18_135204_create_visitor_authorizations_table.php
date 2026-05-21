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
        Schema::create('visitor_authorizations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('visitor_id')
            ->constrained('visitors')
            ->restrictOnDelete();

            $table->foreignId('unit_id')
            ->constrained('units')
            ->restrictOnDelete();

            $table->foreignId('resident_id')
            ->constrained('user_unit')
            ->restrictOnDelete(); //
            
            $table->string('vehicle_plate', 10)->nullable();
            $table->string('access_code')->unique();
            $table->text('qr_code')->nullable();
            $table->string('registration_link')->nullable();

            $table->dateTime('start_date');
            $table->dateTime('end_date');

            $table->enum('status', [
                'pending_data',
                'active',
                'used',
                'expired',
                'canceled',
            ])->default('active');

            $table->timestamp('authorized_date')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_authorizations');
    }
};
