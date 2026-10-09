<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestChange;
use App\Models\ServiceProvider;
use App\Models\Unit;
use App\Models\User;
use App\Services\MaintenanceRequestService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class MaintenanceManagementMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_nullable_migration_rolls_back_only_when_compatible(): void
    {
        $migration = require database_path('migrations/2026_10_08_195540_allow_maintenance_without_resident.php');
        $migration->down();
        $this->assertFalse(collect(Schema::getColumns('maintenance_requests'))->firstWhere('name', 'resident_id')['nullable']);
        $migration->up();
        $request = app(MaintenanceRequestService::class)->createAdministrative(User::factory()->admin()->create(), ['description' => 'Direta', 'unit_id' => Unit::factory()->create()->id]);
        $request->delete();
        try {
            $migration->down();
            $this->fail('Rollback incompatível deveria falhar.');
        } catch (RuntimeException) {
            $this->assertSoftDeleted($request);
        }
        $this->assertTrue(collect(Schema::getColumns('maintenance_requests'))->firstWhere('name', 'resident_id')['nullable']);
    }

    public function test_document_normalization_preserves_archives_and_existing_unique_constraint(): void
    {
        $provider = ServiceProvider::factory()->create(['cpf_cnpj' => '111.444.777-35']);
        $provider->delete();
        $migration = require database_path('migrations/2026_10_08_195541_normalize_service_provider_documents.php');
        $migration->up();
        $this->assertSame('11144477735', $provider->fresh()->cpf_cnpj);
        $this->assertSoftDeleted($provider);
        try {
            DB::transaction(fn () => ServiceProvider::factory()->create(['cpf_cnpj' => '11144477735']));
            $this->fail('Constraint deveria rejeitar.');
        } catch (QueryException) {
            $this->assertSame(1, ServiceProvider::withTrashed()->count());
        }
        ServiceProvider::factory()->count(2)->create(['cpf_cnpj' => null]);
        $this->assertSame(2, ServiceProvider::whereNull('cpf_cnpj')->count());
    }

    public function test_legacy_mask_collisions_abort_before_changing_either_identity(): void
    {
        $first = ServiceProvider::factory()->create(['cpf_cnpj' => '111.444.777-35']);
        $second = ServiceProvider::factory()->create(['cpf_cnpj' => '11144477735']);
        $second->delete();
        $migration = require database_path('migrations/2026_10_08_195541_normalize_service_provider_documents.php');
        try {
            $migration->up();
            $this->fail('Colisão deveria exigir reconciliação.');
        } catch (RuntimeException) {
            $this->assertSame('111.444.777-35', $first->fresh()->cpf_cnpj);
            $this->assertSame('11144477735', $second->fresh()->cpf_cnpj);
        }
    }

    public function test_change_history_migration_is_reversible_and_retains_foreign_key_references(): void
    {
        $migration = require database_path('migrations/2026_10_08_195541_create_maintenance_request_changes_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('maintenance_request_changes'));
        $migration->up();
        $change = MaintenanceRequestChange::factory()->create();
        $this->assertIsArray($change->fresh()->changes);
        foreach ([$change->changedBy, $change->maintenanceRequest] as $model) {
            try {
                DB::transaction(fn () => $model instanceof MaintenanceRequest ? $model->forceDelete() : $model->delete());
                $this->fail('Referência histórica deveria impedir exclusão.');
            } catch (QueryException) {
                $this->assertModelExists($model);
            }
        }
    }
}
