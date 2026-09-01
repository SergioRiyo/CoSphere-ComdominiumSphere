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
        Schema::table('visitor_authorizations', function (Blueprint $table) {
            $table->unsignedBigInteger('visitor_id')->nullable()->change();
            $table->string('access_code')->nullable()->change();
            $table->string('invitation_token_hash', 64)->nullable()->unique();
            $table->timestamp('invitation_expires_at')->nullable();
            $table->timestamp('invitation_used_at')->nullable();
            $table->dropColumn(['qr_code', 'registration_link']);

            $table->index('visitor_id', 'visitor_authorizations_visitor_id_index');
            $table->index(
                ['resident_id', 'status', 'start_date'],
                'visitor_authorizations_resident_status_start_index',
            );
            $table->index(
                ['unit_id', 'status', 'start_date'],
                'visitor_authorizations_unit_status_start_index',
            );
            $table->index('end_date', 'visitor_authorizations_end_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('visitor_authorizations')
            ->where(function ($query): void {
                $query->whereNull('visitor_id')->orWhereNull('access_code');
            })
            ->exists()) {
            throw new RuntimeException(
                'Não é possível reverter convites pendentes sem perder seus dados.',
            );
        }

        Schema::table('visitor_authorizations', function (Blueprint $table) {
            $table->dropIndex('visitor_authorizations_visitor_id_index');
            $table->dropIndex('visitor_authorizations_resident_status_start_index');
            $table->dropIndex('visitor_authorizations_unit_status_start_index');
            $table->dropIndex('visitor_authorizations_end_date_index');
            $table->dropUnique(['invitation_token_hash']);
            $table->dropColumn([
                'invitation_token_hash',
                'invitation_expires_at',
                'invitation_used_at',
            ]);
            $table->text('qr_code')->nullable();
            $table->string('registration_link')->nullable();
            $table->unsignedBigInteger('visitor_id')->nullable(false)->change();
            $table->string('access_code')->nullable(false)->change();
        });
    }
};
