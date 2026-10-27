<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table): void {
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->index(['resident_id', 'status']);
            $table->index(['unit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table): void {
            $table->dropIndex(['resident_id', 'status']);
            $table->dropIndex(['unit_id', 'status']);
            $table->dropConstrainedForeignId('resident_id');
            $table->dropConstrainedForeignId('unit_id');
        });
    }
};
