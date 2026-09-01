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
        $duplicateAuthorizationIds = DB::table('visitor_accesses')
            ->where('validation_status', 'validated')
            ->whereNull('exit_time')
            ->whereNull('deleted_at')
            ->groupBy('visitor_authorization_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('visitor_authorization_id');

        if ($duplicateAuthorizationIds->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Resolva os acessos abertos duplicados das autorizações: %s.',
                $duplicateAuthorizationIds->implode(', '),
            ));
        }

        Schema::table('visitor_accesses', function (Blueprint $table) {
            $table->index(
                'visitor_authorization_id',
                'visitor_accesses_authorization_id_index',
            );
            $table->index(
                ['doorman_id', 'entry_time'],
                'visitor_accesses_doorman_entry_time_index',
            );
            $table->index(
                ['validation_status', 'exit_time'],
                'visitor_accesses_status_exit_time_index',
            );
            $table->index('entry_time', 'visitor_accesses_entry_time_index');
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX visitor_accesses_one_open_per_authorization
            ON visitor_accesses (visitor_authorization_id)
            WHERE validation_status = 'validated'
                AND exit_time IS NULL
                AND deleted_at IS NULL
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS visitor_accesses_one_open_per_authorization');

        Schema::table('visitor_accesses', function (Blueprint $table) {
            $table->dropIndex('visitor_accesses_authorization_id_index');
            $table->dropIndex('visitor_accesses_doorman_entry_time_index');
            $table->dropIndex('visitor_accesses_status_exit_time_index');
            $table->dropIndex('visitor_accesses_entry_time_index');
        });
    }
};
