<?php

namespace Tests\Unit;

use App\Enums\VisitorAuthorizationStatus;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use Tests\TestCase;

class VisitorModelMassAssignmentTest extends TestCase
{
    public function test_security_sensitive_authorization_fields_are_not_mass_assignable(): void
    {
        $authorization = new VisitorAuthorization([
            'visitor_id' => 3,
            'unit_id' => 1,
            'resident_id' => 2,
            'access_code' => 'csa_client_controlled',
            'invitation_token_hash' => 'client-controlled-hash',
            'status' => VisitorAuthorizationStatus::Used,
            'authorized_date' => now(),
            'invitation_expires_at' => now()->addDay(),
            'invitation_used_at' => now(),
        ]);

        $this->assertNull($authorization->visitor_id);
        $this->assertNull($authorization->unit_id);
        $this->assertNull($authorization->resident_id);
        $this->assertNull($authorization->access_code);
        $this->assertNull($authorization->invitation_token_hash);
        $this->assertNull($authorization->status);
        $this->assertNull($authorization->authorized_date);
        $this->assertNull($authorization->invitation_expires_at);
        $this->assertNull($authorization->invitation_used_at);
    }

    public function test_common_fill_cannot_overwrite_service_controlled_authorization_fields(): void
    {
        $authorization = (new VisitorAuthorization)->forceFill([
            'visitor_id' => 1,
            'unit_id' => 2,
            'resident_id' => 3,
            'access_code' => 'csa_'.str_repeat('A', 32),
            'invitation_token_hash' => hash('sha256', 'service-controlled'),
            'status' => VisitorAuthorizationStatus::Active,
            'authorized_date' => now(),
            'invitation_expires_at' => now()->addDay(),
            'invitation_used_at' => null,
        ]);
        $original = $authorization->getAttributes();

        $authorization->fill([
            'visitor_id' => 4,
            'unit_id' => 5,
            'resident_id' => 6,
            'access_code' => 'csa_'.str_repeat('B', 32),
            'invitation_token_hash' => hash('sha256', 'client-controlled'),
            'status' => VisitorAuthorizationStatus::Used,
            'authorized_date' => now()->subDay(),
            'invitation_expires_at' => now()->addDays(2),
            'invitation_used_at' => now(),
        ]);

        $this->assertSame($original, $authorization->getAttributes());
    }

    public function test_security_sensitive_access_fields_are_not_mass_assignable(): void
    {
        $access = new VisitorAccess([
            'visitor_authorization_id' => 1,
            'doorman_id' => 2,
            'exit_doorman_id' => 3,
            'entry_time' => now(),
            'exit_time' => now(),
            'validation_status' => 'validated',
        ]);

        $this->assertNull($access->visitor_authorization_id);
        $this->assertNull($access->doorman_id);
        $this->assertNull($access->exit_doorman_id);
        $this->assertNull($access->entry_time);
        $this->assertNull($access->exit_time);
        $this->assertNull($access->validation_status);
    }
}
