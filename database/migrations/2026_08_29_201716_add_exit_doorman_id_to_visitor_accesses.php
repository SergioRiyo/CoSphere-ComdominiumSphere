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
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('visitor_accesses')
            ->whereNull('doorman_id')
            ->whereNotNull('exit_doorman_id')
            ->update(['doorman_id' => DB::raw('exit_doorman_id')]);

        Schema::table('visitor_accesses', function (Blueprint $table) {
            $table->dropForeign(['exit_doorman_id']);
            $table->dropIndex('visitor_accesses_exit_doorman_id_index');
            $table->dropColumn('exit_doorman_id');
            $table->unsignedBigInteger('doorman_id')->nullable(false)->change();
        });
    }
};
