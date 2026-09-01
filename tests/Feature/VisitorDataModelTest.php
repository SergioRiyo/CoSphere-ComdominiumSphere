<?php

namespace Tests\Feature;

use App\Enums\VisitorAuthorizationStatus;
use App\Models\User;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use App\Services\VisitorService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VisitorDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_invitation_does_not_require_visitor_or_access_code(): void
    {
        $rawToken = 'raw-invitation-token';

        $authorization = VisitorAuthorization::factory()->pendingData($rawToken)->create();

        $this->assertNull($authorization->visitor_id);
        $this->assertNull($authorization->access_code);
        $this->assertSame(hash('sha256', $rawToken), $authorization->invitation_token_hash);
        $this->assertArrayNotHasKey('invitation_token_hash', $authorization->toArray());
        $this->assertFalse(Schema::hasColumn('visitor_authorizations', 'qr_code'));
        $this->assertFalse(Schema::hasColumn('visitor_authorizations', 'registration_link'));
    }

    public function test_invitation_token_hash_must_be_unique(): void
    {
        $tokenHash = hash('sha256', 'duplicated-token');

        VisitorAuthorization::factory()->pendingData()->create([
            'invitation_token_hash' => $tokenHash,
        ]);

        $this->expectException(QueryException::class);

        VisitorAuthorization::factory()->pendingData()->create([
            'invitation_token_hash' => $tokenHash,
        ]);
    }

    public function test_authorization_can_have_only_one_validated_open_access(): void
    {
        $authorization = VisitorAuthorization::factory()->active()->create();

        VisitorAccess::factory()->open()->create([
            'visitor_authorization_id' => $authorization->id,
        ]);

        $this->expectException(QueryException::class);

        VisitorAccess::factory()->open()->create([
            'visitor_authorization_id' => $authorization->id,
        ]);
    }

    public function test_authorization_can_keep_multiple_closed_accesses(): void
    {
        $authorization = VisitorAuthorization::factory()->active()->create();

        VisitorAccess::factory()->closed()->count(2)->create([
            'visitor_authorization_id' => $authorization->id,
        ]);

        $this->assertSame(2, $authorization->visitorAccesses()->count());
    }

    public function test_soft_deleted_open_access_does_not_block_a_new_entry(): void
    {
        $authorization = VisitorAuthorization::factory()->active()->create();
        $access = VisitorAccess::factory()->open()->create([
            'visitor_authorization_id' => $authorization->id,
        ]);

        $access->delete();

        VisitorAccess::factory()->open()->create([
            'visitor_authorization_id' => $authorization->id,
        ]);

        $this->assertSame(1, $authorization->visitorAccesses()->count());
    }

    public function test_active_authorization_requires_visitor_and_access_code(): void
    {
        $authorization = VisitorAuthorization::factory()->pendingData()->create([
            'status' => VisitorAuthorizationStatus::Active,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Autorização sem os dados obrigatórios do visitante.');

        app(VisitorService::class)->validateAuthorization($authorization);
    }

    public function test_registering_exit_preserves_entry_and_exit_doormen(): void
    {
        $authorization = VisitorAuthorization::factory()->active()->create();
        $entryDoorman = User::factory()->porteiro()->create();
        $exitDoorman = User::factory()->porteiro()->create();
        $service = app(VisitorService::class);

        $access = $service->registerEntry($authorization->access_code, $entryDoorman->id);
        $service->registerExit($access, $exitDoorman->id);

        $access->refresh();
        $authorization->refresh();

        $this->assertSame($entryDoorman->id, $access->doorman_id);
        $this->assertSame($exitDoorman->id, $access->exit_doorman_id);
        $this->assertSame(VisitorAuthorizationStatus::Used, $authorization->status);
    }
}
