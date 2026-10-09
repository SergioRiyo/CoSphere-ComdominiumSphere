<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table): void {
            $table->foreignId('resident_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('maintenance_requests')->whereNull('resident_id')->exists()) {
            throw new RuntimeException('Cannot roll back while maintenance without a resident exists, including archived records.');
        }
        Schema::table('maintenance_requests', function (Blueprint $table): void {
            $table->foreignId('resident_id')->nullable(false)->change();
        });
    }
};
