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

    public function test_legacy_qr_payload_is_preserved_and_manual_code_is_stable(): void
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
        $manualCode = $service->manualCode($authorization);

        $this->assertMatchesRegularExpression('/\AM[A-HJ-NP-Z2-9]{12}\z/', $manualCode);
        $this->assertSame($manualCode, $authorization->fresh()->manual_code);
        $this->assertSame($manualCode, $service->manualCode($authorization->fresh()));
        $this->assertStringContainsString('<svg', $service->svg($authorization));
    }

    public function test_current_access_code_is_used_for_both_qr_and_manual_entry(): void
    {
        $authorization = VisitorAuthorization::factory()->future()->create([
            'access_code' => 'ABCDEFGH2345',
        ]);
        $service = app(VisitorQrCodeService::class);

        $this->assertSame('ABCDEFGH2345', $service->payload($authorization));
        $this->assertSame('ABCDEFGH2345', $service->manualCode($authorization));
        $this->assertNull($authorization->fresh()->manual_code);
    }
}
