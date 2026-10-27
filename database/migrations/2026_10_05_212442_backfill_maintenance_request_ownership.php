<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('maintenance_requests')
                ->join('incidents', 'incidents.id', '=', 'maintenance_requests.incident_id')
                ->select(['maintenance_requests.id', 'incidents.unit_id', 'incidents.resident_id'])
                ->lazyById(500, 'maintenance_requests.id', 'id')
                ->each(function (object $request): void {
                    DB::table('maintenance_requests')->where('id', $request->id)->update([
                        'unit_id' => $request->unit_id,
                        'resident_id' => $request->resident_id,
                    ]);
                });
        });
    }

    /** Ownership is retained until the columns themselves are removed. */
    public function down(): void {}
};
