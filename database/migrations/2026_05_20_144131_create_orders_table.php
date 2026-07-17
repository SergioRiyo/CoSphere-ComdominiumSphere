<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('unit_id')
            ->constrained('units')
            ->restrictOnDelete();

            $table->foreignId('resident_id')
            ->constrained('users')
            ->restrictOnDelete();

            $table->foreignId('received_by_id')
            ->nullable()
            ->constrained('users')
            ->restrictOnDelete();

            $table->foreignId('picked_up_by_id')
            ->nullable()
            ->constrained('users')
            ->restrictOnDelete();

            $table->string('tracking_code')->nullable();
            $table->string('sender')->nullable();
            $table->string('carrier')->nullable();
            $table->text('description')->nullable();

            $table->date('expected_delivery_date')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();

            $table->enum('status', [
                'waiting_delivery',
                'received_at_gate',
                'picked_up',
                'cancelled'
            ])->default('waiting_delivery');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
