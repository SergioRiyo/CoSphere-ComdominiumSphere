<?php

namespace Tests\Feature;

use App\Enums\VisitorAuthorizationStatus;
use App\Models\Unit;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ResidentVisitorQrCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_can_retrieve_a_private_qr_svg_and_matching_manual_code(): void
    {
        [$resident, $authorization, $visitor] = $this->createAuthorization();

        $svgResponse = $this->actingAs($resident)
            ->get(route('morador.visitors.qr-code', $authorization));

        $svgResponse
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('<svg', false)
            ->assertDontSee($authorization->access_code)
            ->assertDontSee($visitor->cpf)
            ->assertDontSee($visitor->phone)
            ->assertDontSee('Unidade');

        $this->assertPrivateNoStore($svgResponse);
        $this->assertArrayNotHasKey('access_code', $authorization->toArray());

        $manualCodeResponse = $this->actingAs($resident)
            ->get(route('morador.visitors.access-code', $authorization))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee($authorization->access_code);

        $this->assertPrivateNoStore($manualCodeResponse);
    }

    public function test_resident_can_download_the_qr_code(): void
    {
        [$resident, $authorization] = $this->createAuthorization();

        $this->actingAs($resident)
            ->get(route('morador.visitors.qr-code', [
                'visitorAuthorization' => $authorization,
                'download' => 1,
            ]))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="visitor-authorization-'.$authorization->id.'.svg"');
    }

    public function test_resident_can_retrieve_qr_for_a_future_active_authorization(): void
    {
        [$resident, $authorization] = $this->createAuthorization();
        $authorization->update([
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
        ]);

        $this->actingAs($resident)
            ->get(route('morador.visitors.qr-code', $authorization))
            ->assertOk();

        $this->actingAs($resident)
            ->get(route('morador.visitors.access-code', $authorization))
            ->assertOk()
            ->assertSee($authorization->access_code);
    }

    public function test_resident_from_another_unit_cannot_retrieve_qr_or_manual_code(): void
    {
        [, $authorization] = $this->createAuthorization();
        $otherResident = User::factory()->morador()->create();

        $this->actingAs($otherResident)
            ->get(route('morador.visitors.qr-code', $authorization))
            ->assertForbidden();

        $this->actingAs($otherResident)
            ->get(route('morador.visitors.access-code', $authorization))
            ->assertForbidden();
    }

    public function test_invalid_authorizations_do_not_expose_qr_or_manual_code(): void
    {
        [$resident, $activeAuthorization] = $this->createAuthorization();
        $invalidAuthorizations = [
            VisitorAuthorization::factory()->pendingData()->create([
                'unit_id' => $resident->unit_id,
                'resident_id' => $resident->id,
                'access_code' => 'csa_pending',
            ]),
            VisitorAuthorization::factory()->canceled()->create([
                'unit_id' => $resident->unit_id,
                'resident_id' => $resident->id,
            ]),
            VisitorAuthorization::factory()->expired()->create([
                'unit_id' => $resident->unit_id,
                'resident_id' => $resident->id,
            ]),
            VisitorAuthorization::factory()->used()->create([
                'unit_id' => $resident->unit_id,
                'resident_id' => $resident->id,
            ]),
            VisitorAuthorization::factory()->active()->create([
                'visitor_id' => $activeAuthorization->visitor_id,
                'unit_id' => $resident->unit_id,
                'resident_id' => $resident->id,
                'status' => VisitorAuthorizationStatus::Active,
                'start_date' => now()->subHours(2),
                'end_date' => now()->subMinute(),
            ]),
        ];

        foreach ($invalidAuthorizations as $authorization) {
            $this->actingAs($resident)
                ->get(route('morador.visitors.qr-code', $authorization))
                ->assertNotFound();

            $this->actingAs($resident)
                ->get(route('morador.visitors.access-code', $authorization))
                ->assertNotFound();
        }
    }

    /**
     * @return array{0: User, 1: VisitorAuthorization, 2: Visitor}
     */
    private function createAuthorization(): array
    {
        $unit = Unit::factory()->create(['status' => 'active']);
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $visitor = Visitor::factory()->create([
            'cpf' => '529.982.247-25',
            'phone' => '(65) 99999-9999',
        ]);
        $authorization = VisitorAuthorization::factory()->active()->create([
            'visitor_id' => $visitor->id,
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
        ]);

        return [$resident, $authorization, $visitor];
    }

    private function assertPrivateNoStore(TestResponse $response): void
    {
        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
    }
}
