<?php

namespace Tests\Feature;

use App\Enums\IncidentType;
use App\Enums\MaintenanceRequestStatus;
use App\Models\Incident;
use App\Models\IncidentAttachment;
use App\Models\IncidentPriorityHistory;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class OccurrenceMaintenanceMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_priority_history_migration_can_rollback_and_reapply_without_changing_incidents(): void
    {
        $incident = Incident::factory()->create()->refresh();
        $before = $incident->getRawOriginal();
        $migration = require database_path('migrations/2026_10_06_162938_create_incident_priority_histories_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('incident_priority_histories'));
        $this->assertSame($before, $incident->fresh()->getRawOriginal());
        $migration->up();
        $this->assertTrue(Schema::hasTable('incident_priority_histories'));
        $history = IncidentPriorityHistory::factory()->create(['incident_id' => $incident->id]);
        $this->assertSame($incident->id, $history->incident->id);
        $this->assertSame($before, $incident->fresh()->getRawOriginal());
    }

    public function test_incremental_upgrade_preserves_all_legacy_values_links_and_soft_deletes(): void
    {
        $migrations = $this->migrations();
        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }
        $resident = User::factory()->morador()->create();
        foreach (['maintenance', 'legacy/electrical'] as $category) {
            $incidentId = DB::table('incidents')->insertGetId([
                'unit_id' => $resident->unit_id, 'resident_id' => $resident->id,
                'title' => 'Registro legado', 'description' => 'Preservar conteúdo', 'category' => $category,
                'status' => 'in_progress', 'priority' => 'high',
                'opened_at' => '2026-01-01 10:00:00', 'created_at' => '2026-01-01 10:00:00',
                'updated_at' => '2026-01-02 10:00:00', 'deleted_at' => '2026-01-03 10:00:00',
            ]);
            DB::table('maintenance_requests')->insert([
                'incident_id' => $incidentId, 'service_provider_id' => null, 'admin_id' => null,
                'description' => 'Manutenção legada', 'status' => 'pending',
                'scheduled_at' => null, 'executed_at' => null, 'cost' => '123.45',
                'created_at' => '2026-01-02 10:00:00', 'updated_at' => '2026-01-02 11:00:00',
                'deleted_at' => '2026-01-03 10:00:00',
            ]);
        }
        $incidentsBefore = DB::table('incidents')->orderBy('id')->get()->toArray();
        $maintenanceBefore = DB::table('maintenance_requests')->orderBy('id')->get()->toArray();
        foreach ($migrations as $migration) {
            $migration->up();
        }
        foreach ($incidentsBefore as $before) {
            $after = (array) DB::table('incidents')->find($before->id);
            $this->assertSame('incident', $after['type']);
            unset($after['type']);
            $this->assertEquals((array) $before, $after);
            $incident = Incident::withTrashed()->findOrFail($before->id);
            $this->assertSame(IncidentType::Incident, $incident->type);
            $this->assertSame($before->category, $incident->getRawOriginal('category'));
            $this->assertNotNull($incident->category);
        }
        foreach ($maintenanceBefore as $before) {
            $after = (array) DB::table('maintenance_requests')->find($before->id);
            $this->assertSame($resident->id, $after['resident_id']);
            $this->assertSame($resident->unit_id, $after['unit_id']);
            unset($after['unit_id'], $after['resident_id']);
            $this->assertEquals((array) $before, $after);
            $request = MaintenanceRequest::withTrashed()->findOrFail($before->id);
            $this->assertSame($before->incident_id, $request->incident->id);
            $this->assertSame('123.45', $request->cost);
        }
        $this->assertDatabaseCount('incident_status_histories', 0);
        $this->assertDatabaseCount('maintenance_request_status_histories', 0);
        $this->assertDatabaseCount('incident_attachments', 0);
    }

    public function test_compatible_nullable_rollback_restores_requirement_and_can_be_upgraded_again(): void
    {
        $request = MaintenanceRequest::factory()->linkedToIncident()->create()->refresh();
        $before = $request->getRawOriginal();
        $migration = $this->independentMigration();
        $migration->down();
        $column = collect(Schema::getColumns('maintenance_requests'))->firstWhere('name', 'incident_id');
        $this->assertFalse($column['nullable']);
        try {
            DB::transaction(fn () => DB::table('maintenance_requests')->where('id', $request->id)->update(['incident_id' => null]));
            $this->fail('Incident must be required after rollback.');
        } catch (QueryException) {
            $this->assertSame($before, $request->refresh()->getRawOriginal());
        }
        $migration->up();
        $column = collect(Schema::getColumns('maintenance_requests'))->firstWhere('name', 'incident_id');
        $this->assertTrue($column['nullable']);
        $request->update(['incident_id' => null]);
        $this->assertNull($request->refresh()->incident);
    }

    #[DataProvider('independentRows')]
    public function test_incompatible_rollback_aborts_before_any_schema_or_data_change(bool $softDeleted): void
    {
        $request = MaintenanceRequest::factory()->withoutIncident()->create();
        if ($softDeleted) {
            $request->delete();
        }
        $attachment = IncidentAttachment::factory()->create();
        $before = DB::table('maintenance_requests')->orderBy('id')->get()->toJson();
        $columns = Schema::getColumns('maintenance_requests');
        try {
            foreach (array_reverse($this->migrations()) as $migration) {
                $migration->down();
            }
            $this->fail('Independent requests must prevent unsafe rollback.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('without an incident', $exception->getMessage());
            $this->assertStringContainsString('No records or links were changed', $exception->getMessage());
        }
        $this->assertSame($columns, Schema::getColumns('maintenance_requests'));
        $this->assertSame($before, DB::table('maintenance_requests')->orderBy('id')->get()->toJson());
        $this->assertModelExists($attachment);
        $this->assertTrue(Schema::hasTable('incident_status_histories'));
        $this->assertTrue(Schema::hasTable('maintenance_request_status_histories'));
    }

    public static function independentRows(): array
    {
        return [[false], [true]];
    }

    public function test_upgrade_refuses_missing_ownership_before_changing_incident_nullability(): void
    {
        $migration = $this->independentMigration();
        $migration->down();
        $request = MaintenanceRequest::factory()->linkedToIncident()->create();
        DB::table('maintenance_requests')->where('id', $request->id)->update(['resident_id' => null]);
        $columns = Schema::getColumns('maintenance_requests');
        try {
            $migration->up();
            $this->fail('Missing ownership requires reconciliation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ownership must be reconciled', $exception->getMessage());
        }
        $this->assertSame($columns, Schema::getColumns('maintenance_requests'));
        $this->assertNull(DB::table('maintenance_requests')->find($request->id)->resident_id);
    }

    public function test_required_context_foreign_keys_and_lookup_indexes_survive_column_changes(): void
    {
        $request = MaintenanceRequest::factory()->linkedToIncident()->create();
        $expectedReferences = ['incidents', 'units', 'users', 'service_providers'];
        $actual = collect(Schema::getForeignKeys('maintenance_requests'))->pluck('foreign_table')->all();
        foreach ($expectedReferences as $table) {
            $this->assertContains($table, $actual);
        }
        $indexes = collect(Schema::getIndexes('maintenance_requests'))->pluck('columns')->all();
        $this->assertContains(['incident_id', 'status'], $indexes);
        $this->assertContains(['resident_id', 'status'], $indexes);
        $this->assertContains(['unit_id', 'status'], $indexes);
        foreach (['incident_id' => 999999, 'resident_id' => null, 'unit_id' => null] as $field => $value) {
            try {
                DB::transaction(fn () => DB::table('maintenance_requests')->where('id', $request->id)->update([$field => $value]));
                $this->fail('Required ownership and valid foreign keys must be enforced.');
            } catch (QueryException) {
                $this->assertModelExists($request);
            }
        }
        $this->assertSame(MaintenanceRequestStatus::Pending, $request->refresh()->status);
    }

    /** @return list<Migration> */
    private function migrations(): array
    {
        return array_map(fn (string $file): Migration => require database_path('migrations/'.$file), [
            '2026_10_05_212433_create_incident_status_histories_table.php',
            '2026_10_05_212434_create_incident_attachments_table.php',
            '2026_10_05_212434_create_maintenance_request_status_histories_table.php',
            '2026_10_05_212441_add_type_to_incidents_table.php',
            '2026_10_05_212442_add_ownership_to_maintenance_requests_table.php',
            '2026_10_05_212442_backfill_maintenance_request_ownership.php',
            '2026_10_05_212443_allow_independent_maintenance_requests.php',
        ]);
    }

    private function independentMigration(): Migration
    {
        return require database_path('migrations/2026_10_05_212443_allow_independent_maintenance_requests.php');
    }
}
