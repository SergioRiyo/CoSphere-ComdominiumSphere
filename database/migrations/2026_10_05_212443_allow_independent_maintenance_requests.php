<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('maintenance_requests')->whereNull('unit_id')->orWhereNull('resident_id')->exists()) {
            throw new RuntimeException('Maintenance ownership must be reconciled before enabling independent requests.');
        }

        Schema::table('maintenance_requests', function (Blueprint $table): void {
            $table->foreignId('incident_id')->nullable()->change();
            $table->foreignId('unit_id')->nullable(false)->change();
            $table->foreignId('resident_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (DB::table('maintenance_requests')->whereNull('incident_id')->exists()) {
            throw new RuntimeException('Cannot roll back: maintenance requests without an incident exist, including soft-deleted records. No records or links were changed.');
        }

        Schema::table('maintenance_requests', function (Blueprint $table): void {
            $table->foreignId('incident_id')->nullable(false)->change();
            $table->foreignId('unit_id')->nullable()->change();
            $table->foreignId('resident_id')->nullable()->change();
        });
    }
};
