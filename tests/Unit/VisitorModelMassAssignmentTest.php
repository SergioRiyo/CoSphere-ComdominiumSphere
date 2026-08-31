<?php

namespace Tests\Unit;

use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use Tests\TestCase;

class VisitorModelMassAssignmentTest extends TestCase
{
    public function test_security_sensitive_authorization_fields_are_not_mass_assignable(): void
    {
        $authorization = new VisitorAuthorization([
            'unit_id' => 1,
            'resident_id' => 2,
            'access_code' => 'csa_client_controlled',
            'invitation_token_hash' => 'client-controlled-hash',
        ]);

        $this->assertNull($authorization->unit_id);
        $this->assertNull($authorization->resident_id);
        $this->assertNull($authorization->access_code);
        $this->assertNull($authorization->invitation_token_hash);
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
