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
            $table->foreignId('unit_id')->nullable()->change();
            $table->foreignId('common_area_id')->nullable()->after('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('common_area_block_id')->nullable()->unique()->after('common_area_id')->constrained()->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE maintenance_requests ADD CONSTRAINT maintenance_requests_target_check CHECK ((unit_id IS NULL) <> (common_area_id IS NULL))');
        }
    }

    public function down(): void
    {
        if (DB::table('maintenance_requests')->whereNotNull('common_area_id')->exists()) {
            throw new RuntimeException('Cannot roll back while common-area maintenance requests exist.');
        }

        Schema::table('maintenance_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('common_area_block_id');
            $table->dropConstrainedForeignId('common_area_id');
            $table->foreignId('unit_id')->nullable(false)->change();
        });
    }
};
