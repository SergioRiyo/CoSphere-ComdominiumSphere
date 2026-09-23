<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VisitorAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class VisitorExitDoormanMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollback_refuses_to_discard_operator_history_before_changing_schema_or_data(): void
    {
        $entryDoorman = User::factory()->porteiro()->create();
        $exitDoorman = User::factory()->porteiro()->create();
        VisitorAccess::factory()->closed($exitDoorman)->create(['doorman_id' => $entryDoorman->id]);
        VisitorAccess::factory()->closed($exitDoorman)->create(['doorman_id' => null]);
        VisitorAccess::factory()->open()->create(['doorman_id' => $entryDoorman->id]);
        $before = DB::table('visitor_accesses')->orderBy('id')->get()->all();
        $columns = Schema::getColumns('visitor_accesses');
        $indexes = Schema::getIndexes('visitor_accesses');
        $foreignKeys = Schema::getForeignKeys('visitor_accesses');
        $migration = require database_path('migrations/2026_08_29_201716_add_exit_doorman_id_to_visitor_accesses.php');

        try {
            $migration->down();
            $this->fail('Rollback must not choose between the entry and exit operators.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Cannot roll back exit_doorman_id', $exception->getMessage());
            $this->assertStringContainsString('forward migration', $exception->getMessage());
        }

        $this->assertEquals($before, DB::table('visitor_accesses')->orderBy('id')->get()->all());
        $this->assertSame($columns, Schema::getColumns('visitor_accesses'));
        $this->assertSame($indexes, Schema::getIndexes('visitor_accesses'));
        $this->assertSame($foreignKeys, Schema::getForeignKeys('visitor_accesses'));
    }
}
