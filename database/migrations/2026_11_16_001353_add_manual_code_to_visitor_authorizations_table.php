<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('visitor_authorizations', function (Blueprint $table) {
            $table->string('manual_code', 13)->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visitor_authorizations', function (Blueprint $table) {
            $table->dropUnique(['manual_code']);
            $table->dropColumn('manual_code');
        });
    }
};
