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
        DB::table('reservations')->where('status', 'completed')->update(['status' => 'confirmed']);
        DB::table('reservation_status_histories')->where('from_status', 'completed')->update(['from_status' => 'confirmed']);
        DB::table('reservation_status_histories')->where('to_status', 'completed')->update(['to_status' => 'confirmed']);

        if (DB::getDriverName() === 'pgsql') {
            $this->replacePostgresStatusConstraints(['pending', 'confirmed', 'cancelled', 'rejected']);

            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'rejected'])
                ->default('confirmed')
                ->change();
        });
        Schema::table('reservation_status_histories', function (Blueprint $table) {
            $table->enum('from_status', ['pending', 'confirmed', 'rejected', 'cancelled'])->nullable()->change();
            $table->enum('to_status', ['pending', 'confirmed', 'rejected', 'cancelled'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->replacePostgresStatusConstraints(['pending', 'confirmed', 'cancelled', 'rejected', 'completed']);

            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'rejected', 'completed'])
                ->default('confirmed')
                ->change();
        });
        Schema::table('reservation_status_histories', function (Blueprint $table) {
            $table->enum('from_status', ['pending', 'confirmed', 'rejected', 'cancelled', 'completed'])->nullable()->change();
            $table->enum('to_status', ['pending', 'confirmed', 'rejected', 'cancelled', 'completed'])->change();
        });
    }

    /** @param list<string> $statuses */
    private function replacePostgresStatusConstraints(array $statuses): void
    {
        $allowedStatuses = implode(', ', array_map(fn (string $status): string => DB::getPdo()->quote($status), $statuses));

        DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_status_check');
        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_status_check CHECK (status IN ({$allowedStatuses}))");
        DB::statement('ALTER TABLE reservation_status_histories DROP CONSTRAINT IF EXISTS reservation_status_histories_from_status_check');
        DB::statement("ALTER TABLE reservation_status_histories ADD CONSTRAINT reservation_status_histories_from_status_check CHECK (from_status IN ({$allowedStatuses}))");
        DB::statement('ALTER TABLE reservation_status_histories DROP CONSTRAINT IF EXISTS reservation_status_histories_to_status_check');
        DB::statement("ALTER TABLE reservation_status_histories ADD CONSTRAINT reservation_status_histories_to_status_check CHECK (to_status IN ({$allowedStatuses}))");
    }
};
