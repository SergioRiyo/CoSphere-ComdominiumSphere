<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('LOCK TABLE visitors, visitor_authorizations IN SHARE ROW EXCLUSIVE MODE');
            }

            // Include soft-deleted authorizations in the historical ownership check.
            $sharedVisitorIds = DB::table('visitor_authorizations')
                ->whereNotNull('visitor_id')
                ->groupBy('visitor_id')
                ->havingRaw('COUNT(DISTINCT unit_id) > 1')
                ->pluck('visitor_id');

            if ($sharedVisitorIds->isNotEmpty()) {
                throw new RuntimeException(
                    'Visitor isolation requires manual reconciliation of shared visitor IDs: '
                    .$sharedVisitorIds->implode(', ').'. No PII can be safely attributed automatically.',
                );
            }

            $unassignedVisitorIds = DB::table('visitors')
                ->whereNotExists(DB::table('visitor_authorizations')->selectRaw('1')->whereColumn('visitor_id', 'visitors.id'))
                ->pluck('id');

            if ($unassignedVisitorIds->isNotEmpty()) {
                throw new RuntimeException(
                    'Visitor isolation requires an identified unit for unreferenced visitor IDs: '
                    .$unassignedVisitorIds->implode(', ').'. No unit can be assigned automatically.',
                );
            }

            if (DB::getDriverName() === 'sqlite') {
                // Inline references avoid rebuilding a parent table with historical foreign keys.
                DB::statement('ALTER TABLE visitors ADD COLUMN unit_id INTEGER REFERENCES units(id) ON DELETE RESTRICT');
            } else {
                Schema::table('visitors', function (Blueprint $table): void {
                    $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
                });
            }

            Schema::table('visitors', function (Blueprint $table): void {
                $table->dropUnique('visitors_cpf_unique');
            });

            DB::table('visitors')->update([
                'unit_id' => DB::table('visitor_authorizations')
                    ->select('unit_id')
                    ->whereColumn('visitor_id', 'visitors.id')
                    ->limit(1),
            ]);

            DB::table('visitors')->select(['id', 'cpf'])->chunkById(500, function (Collection $visitors): void {
                foreach ($visitors as $visitor) {
                    $digits = (string) preg_replace('/\D/', '', $visitor->cpf);

                    if (strlen($digits) === 11) {
                        DB::table('visitors')->where('id', $visitor->id)->update([
                            'cpf' => sprintf('%s.%s.%s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 3), substr($digits, 9, 2)),
                        ]);
                    }
                }
            });

            if (DB::table('visitors')->whereNotNull('unit_id')
                ->selectRaw('1')
                ->groupBy('unit_id', 'cpf')->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException('Visitor isolation requires reconciliation of duplicate normalized CPFs within a unit.');
            }

            $this->changeVisitorTable(function (Blueprint $table): void {
                $table->unsignedBigInteger('unit_id')->nullable(false)->change();
                $table->unique(['unit_id', 'cpf']);
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function (): void {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('LOCK TABLE visitors, visitor_authorizations IN SHARE ROW EXCLUSIVE MODE');
            }

            if (DB::table('visitors')->selectRaw('1')->groupBy('cpf')->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException('Cannot restore global CPF uniqueness without merging personal data. Use a forward migration.');
            }

            $this->changeVisitorTable(function (Blueprint $table): void {
                $table->dropUnique('visitors_unit_id_cpf_unique');
                $table->dropForeign(['unit_id']);
                $table->dropColumn('unit_id');
                $table->unique('cpf');
            });
        });
    }

    private function changeVisitorTable(Closure $callback): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('visitors', $callback);

            return;
        }

        // SQLite rebuilds the parent table for NOT NULL changes. Check every FK before restoring deferral.
        $deferred = (int) DB::scalar('PRAGMA defer_foreign_keys');
        DB::statement('PRAGMA defer_foreign_keys = ON');

        try {
            Schema::table('visitors', $callback);

            if (DB::select('PRAGMA foreign_key_check') !== []) {
                throw new RuntimeException('Visitor migration would leave invalid historical foreign keys.');
            }
        } finally {
            DB::statement('PRAGMA defer_foreign_keys = '.$deferred);
        }
    }
};
