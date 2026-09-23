<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('visitor_accesses', function (Blueprint $table) {
            $table->unsignedBigInteger('doorman_id')->nullable()->change();
            $table->foreignId('exit_doorman_id')
                ->nullable()
                ->after('doorman_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->index('exit_doorman_id', 'visitor_accesses_exit_doorman_id_index');
        });

        // Legacy exits overwrote doorman_id, so it is also the only known exit doorman.
        DB::table('visitor_accesses')
            ->whereNotNull('exit_time')
            ->whereNull('exit_doorman_id')
            ->update(['exit_doorman_id' => DB::raw('doorman_id')]);

        DB::table('visitor_accesses')
            ->whereNotNull('exit_time')
            ->update(['doorman_id' => null]);
    }

    /**
     * The legacy schema cannot preserve both entry and exit operators.
     * Reconciliation requires an explicit forward migration, not a lossy rollback.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'Cannot roll back exit_doorman_id without losing entry or exit operator history. Use an explicit forward migration after reconciliation.',
        );
    }
};
