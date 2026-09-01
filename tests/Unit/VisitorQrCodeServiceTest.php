<?php

namespace Tests\Unit;

use App\Models\Unit;
use App\Models\User;
use App\Models\VisitorAuthorization;
use App\Services\VisitorQrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorQrCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_payload_and_manual_code_are_the_authorization_access_code(): void
    {
        $unit = Unit::factory()->create(['status' => 'active']);
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $authorization = VisitorAuthorization::factory()->future()->create([
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
            'access_code' => 'csa_qr_payload',
        ]);

        $service = app(VisitorQrCodeService::class);

        $this->assertSame('csa_qr_payload', $service->payload($authorization));
        $this->assertSame('csa_qr_payload', $service->manualCode($authorization));
        $this->assertStringContainsString('<svg', $service->svg($authorization));
    }
}
