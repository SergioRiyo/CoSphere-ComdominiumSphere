<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('description', 200);
            $table->unsignedBigInteger('amount_cents');
            $table->date('due_date');
            $table->string('status')->default('open');
            $table->date('paid_at')->nullable();
            $table->timestamps();
            $table->index(['unit_id', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_charges');
    }
};
