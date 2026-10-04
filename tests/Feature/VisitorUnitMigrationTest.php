<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\Visitor;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class VisitorUnitMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_migration_roundtrip_uses_grouped_projections_compatible_with_postgresql(): void
    {
        DB::enableQueryLog();

        try {
            $migration = $this->migration();
            $migration->down();
            $migration->up();

            $duplicateChecks = collect(DB::getQueryLog())
                ->pluck('query')
                ->filter(fn (string $sql): bool => str_contains($sql, 'select exists(') && str_contains($sql, 'group by'));

            $this->assertCount(2, $duplicateChecks);

            foreach ($duplicateChecks as $sql) {
                $this->assertStringContainsString('select exists(select 1 from "visitors"', $sql);
            }

            $this->assertTrue(Schema::hasColumn('visitors', 'unit_id'));
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_backfill_uses_the_only_historical_unit_including_soft_deleted_authorizations(): void
    {
        $migration = $this->migration();
        $migration->down();
        $unit = Unit::factory()->create();
        $visitorId = $this->legacyVisitor('52998224725');
        $authorization = VisitorAuthorization::factory()->create([
            'visitor_id' => $visitorId,
            'unit_id' => $unit->id,
        ]);
        $authorization->delete();

        $migration->up();

        $this->assertDatabaseHas('visitors', [
            'id' => $visitorId,
            'unit_id' => $unit->id,
            'cpf' => '529.982.247-25',
            'name' => 'Visitante histórico',
            'phone' => '(65) 99999-9999',
        ]);
        $this->assertSoftDeleted('visitor_authorizations', ['id' => $authorization->id]);
    }

    public function test_unreferenced_legacy_visitor_aborts_before_schema_or_data_changes(): void
    {
        $migration = $this->migration();
        $migration->down();
        $visitorId = $this->legacyVisitor('529.982.247-25');
        DB::table('visitors')->where('id', $visitorId)->update(['deleted_at' => now()]);
        $before = $this->historicalRows();

        try {
            $migration->up();
            $this->fail('An unreferenced visitor must require manual reconciliation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('unreferenced visitor IDs: '.$visitorId, $exception->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('visitors', 'unit_id'));
        $this->assertEquals($before, $this->historicalRows());
    }

    public function test_shared_visitor_aborts_before_schema_or_historical_data_changes(): void
    {
        $migration = $this->migration();
        $migration->down();
        $visitorId = $this->legacyVisitor('52998224725');
        $firstAuthorization = VisitorAuthorization::factory()->create([
            'visitor_id' => $visitorId,
            'unit_id' => Unit::factory()->create()->id,
        ]);
        VisitorAuthorization::factory()->create([
            'visitor_id' => $visitorId,
            'unit_id' => Unit::factory()->create()->id,
        ])->delete();
        VisitorAccess::factory()->closed()->create([
            'visitor_authorization_id' => $firstAuthorization->id,
        ]);
        $before = $this->historicalRows();

        try {
            $migration->up();
            $this->fail('A visitor shared by multiple units must require manual reconciliation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('shared visitor IDs: '.$visitorId, $exception->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('visitors', 'unit_id'));
        $this->assertEquals($before, $this->historicalRows());
    }

    public function test_normalized_cpf_collision_within_a_unit_rolls_back_schema_and_data(): void
    {
        $migration = $this->migration();
        $migration->down();
        $unit = Unit::factory()->create();

        foreach (['52998224725', '529.982.247-25'] as $cpf) {
            VisitorAuthorization::factory()->create([
                'visitor_id' => $this->legacyVisitor($cpf),
                'unit_id' => $unit->id,
            ]);
        }

        $before = $this->historicalRows();

        try {
            $migration->up();
            $this->fail('Normalized CPF collisions must require reconciliation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('duplicate normalized CPFs within a unit', $exception->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('visitors', 'unit_id'));
        $this->assertEquals($before, $this->historicalRows());
    }

    public function test_unique_index_allows_the_same_cpf_in_different_units(): void
    {
        $firstVisitor = Visitor::factory()->create(['cpf' => '529.982.247-25']);
        $secondVisitor = Visitor::factory()->create(['cpf' => '529.982.247-25']);

        $this->assertNotSame($firstVisitor->unit_id, $secondVisitor->unit_id);
        $this->assertDatabaseCount('visitors', 2);
    }

    public function test_unit_foreign_key_rejects_a_nonexistent_unit(): void
    {
        $missingUnitId = ((int) Unit::query()->max('id')) + 1;

        $this->expectException(QueryException::class);

        Visitor::factory()->create(['unit_id' => $missingUnitId]);
    }

    public function test_database_rejects_a_visitor_without_a_unit(): void
    {
        $this->expectException(QueryException::class);

        Visitor::factory()->create(['unit_id' => null]);
    }

    public function test_unit_foreign_key_restricts_deletion_of_a_visitor_unit(): void
    {
        $visitor = Visitor::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('units')->where('id', $visitor->unit_id)->delete();
    }

    public function test_unique_index_rejects_the_same_cpf_within_a_unit(): void
    {
        $visitor = Visitor::factory()->create(['cpf' => '529.982.247-25']);

        $this->expectException(QueryException::class);

        Visitor::factory()->create([
            'unit_id' => $visitor->unit_id,
            'cpf' => $visitor->cpf,
        ]);
    }

    public function test_unique_index_also_protects_soft_deleted_visitors(): void
    {
        $visitor = Visitor::factory()->create(['cpf' => '529.982.247-25']);
        $visitor->delete();

        $this->expectException(QueryException::class);

        Visitor::factory()->create([
            'unit_id' => $visitor->unit_id,
            'cpf' => $visitor->cpf,
        ]);
    }

    public function test_rollback_refuses_repeated_cpfs_without_losing_data_or_unit_scope(): void
    {
        foreach (range(1, 2) as $index) {
            $visitor = Visitor::factory()->create(['cpf' => '529.982.247-25']);
            $authorization = VisitorAuthorization::factory()->create(['visitor_id' => $visitor->id]);
            VisitorAccess::factory()->closed()->create(['visitor_authorization_id' => $authorization->id]);
        }

        $before = $this->historicalRows();

        try {
            $this->migration()->down();
            $this->fail('Rollback must not merge visitors from different units.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Cannot restore global CPF uniqueness', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('visitors', 'unit_id'));
        $this->assertEquals($before, $this->historicalRows());
    }

    public function test_unique_legacy_data_survives_a_migration_roundtrip_with_authorizations_and_accesses(): void
    {
        $migration = $this->migration();
        $migration->down();
        $visitorId = $this->legacyVisitor('529.982.247-25');
        $authorization = VisitorAuthorization::factory()->create([
            'visitor_id' => $visitorId,
            'unit_id' => Unit::factory()->create()->id,
        ]);
        VisitorAccess::factory()->closed()->create(['visitor_authorization_id' => $authorization->id]);
        $legacyRows = $this->historicalRows();

        $migration->up();
        $scopedRows = $this->historicalRows();
        $migration->down();

        $this->assertEquals($legacyRows, $this->historicalRows());

        $migration->up();

        $this->assertEquals($scopedRows, $this->historicalRows());
        $this->assertSame($authorization->unit_id, Visitor::query()->findOrFail($visitorId)->unit_id);
    }

    public function test_sqlite_backfill_commits_with_historical_foreign_keys_and_not_null_unit(): void
    {
        $connectionName = 'visitor_migration_commit';
        $originalDefault = DB::getDefaultConnection();
        $originalSchema = Schema::getFacadeRoot();
        config()->set('database.connections.'.$connectionName, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        try {
            DB::setDefaultConnection($connectionName);
            Schema::swap(DB::connection($connectionName)->getSchemaBuilder());
            $this->assertSame(0, Artisan::call('migrate', [
                '--database' => $connectionName,
                '--force' => true,
                '--no-interaction' => true,
            ]));
            $this->assertSame(0, DB::transactionLevel());
            $migration = $this->migration();
            $migration->down();
            $visitorId = $this->legacyVisitor('529.982.247-25');
            $authorization = VisitorAuthorization::factory()->create([
                'visitor_id' => $visitorId,
                'unit_id' => Unit::factory()->create()->id,
            ]);
            VisitorAccess::factory()->closed()->create(['visitor_authorization_id' => $authorization->id]);
            $authorization->delete();
            $legacyRows = $this->historicalRows();

            $migration->up();

            $this->assertSame(0, DB::transactionLevel());
            $this->assertSame(1, (int) DB::scalar('PRAGMA foreign_keys'));
            $this->assertSame(0, (int) DB::scalar('PRAGMA defer_foreign_keys'));
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
            $unitColumn = collect(DB::select('PRAGMA table_info(visitors)'))->firstWhere('name', 'unit_id');
            $this->assertSame(1, $unitColumn->notnull);
            $this->assertSame($authorization->unit_id, Visitor::query()->findOrFail($visitorId)->unit_id);
            $this->assertEquals($legacyRows['visitor_authorizations'], $this->historicalRows()['visitor_authorizations']);
            $this->assertEquals($legacyRows['visitor_accesses'], $this->historicalRows()['visitor_accesses']);

            $migration->down();

            $this->assertSame(0, DB::transactionLevel());
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
            $this->assertEquals($legacyRows, $this->historicalRows());
        } finally {
            DB::setDefaultConnection($originalDefault);
            Schema::swap($originalSchema);
            DB::purge($connectionName);
            config()->set('database.connections.'.$connectionName, null);
        }
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_09_06_211958_scope_visitors_to_units.php');
    }

    private function legacyVisitor(string $cpf): int
    {
        return DB::table('visitors')->insertGetId([
            'name' => 'Visitante histórico',
            'cpf' => $cpf,
            'phone' => '(65) 99999-9999',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }

    /**
     * @return array<string, array<int, object>>
     */
    private function historicalRows(): array
    {
        return [
            'visitors' => DB::table('visitors')->orderBy('id')->get()->all(),
            'visitor_authorizations' => DB::table('visitor_authorizations')->orderBy('id')->get()->all(),
            'visitor_accesses' => DB::table('visitor_accesses')->orderBy('id')->get()->all(),
        ];
    }
}
