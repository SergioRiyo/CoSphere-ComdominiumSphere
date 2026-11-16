<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateTypes(['reservation', 'visitor', 'package', 'occurrence', 'system', 'announcement']);

        Schema::table('notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('announcement_id')->nullable();
            $table->unique(['announcement_id', 'recipient_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('notifications')->where('type', 'announcement')->orWhereNotNull('announcement_id')->exists()) {
            throw new RuntimeException('Cannot roll back while announcement notifications exist.');
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique(['announcement_id', 'recipient_id']);
            $table->dropColumn('announcement_id');
        });

        $this->updateTypes(['reservation', 'visitor', 'package', 'occurrence', 'system']);
    }

    /** @param list<string> $types */
    private function updateTypes(array $types): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $allowedTypes = implode(', ', array_map(fn (string $type): string => DB::getPdo()->quote($type), $types));
            DB::statement('ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_type_check');
            DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_type_check CHECK (type IN ({$allowedTypes}))");

            return;
        }

        Schema::table('notifications', function (Blueprint $table) use ($types) {
            $table->enum('type', $types)->change();
        });
    }
};
