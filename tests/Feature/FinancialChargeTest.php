<?php

namespace Tests\Feature;

use App\Models\FinancialCharge;
use App\Models\FinancialChargeHistory;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\FinancialChargeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancialChargeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00'));
    }

    public function test_residents_share_unit_charges_without_access_to_other_units_or_history(): void
    {
        $resident = User::factory()->morador()->create();
        $sameUnit = User::factory()->morador()->create(['unit_id' => $resident->unit_id]);
        $own = FinancialCharge::factory()->create(['unit_id' => $resident->unit_id]);
        $other = FinancialCharge::factory()->create();
        $this->actingAs($sameUnit)->get(route('morador.financial.index'))->assertInertia(fn (Assert $page) => $page
            ->component('financial/index')->has('charges.data', 1)->where('charges.data.0.id', $own->id));
        $this->get(route('morador.financial.show', $own))->assertInertia(fn (Assert $page) => $page->missing('charge.history'));
        $this->get(route('morador.financial.show', $other))->assertForbidden();
        $this->get(route('morador.financial.boleto', $other))->assertForbidden();
        $this->post(route('admin.financial.store'), [])->assertForbidden();
        $sameUnit->update(['unit_id' => null]);
        $this->get(route('morador.financial.index'))->assertInertia(fn (Assert $page) => $page->has('charges.data', 0));
    }

    public function test_due_date_status_filters_and_summary_exclude_paid_and_cancelled(): void
    {
        $unit = Unit::factory()->create();
        FinancialCharge::factory()->create(['unit_id' => $unit->id, 'due_date' => '2026-11-13', 'amount_cents' => 15000]);
        FinancialCharge::factory()->create(['unit_id' => $unit->id, 'due_date' => '2026-11-14', 'amount_cents' => 10000]);
        FinancialCharge::factory()->create(['unit_id' => $unit->id, 'due_date' => '2026-11-10', 'amount_cents' => 5000, 'status' => 'paid', 'paid_at' => '2026-11-10']);
        FinancialCharge::factory()->create(['unit_id' => $unit->id, 'due_date' => '2026-11-10', 'amount_cents' => 8000, 'status' => 'cancelled']);
        FinancialCharge::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.financial.index', ['unit_id' => $unit->id, 'status' => 'overdue']))
            ->assertInertia(fn (Assert $page) => $page->where('summary.open_cents', 25000)->where('summary.overdue_cents', 15000)
                ->where('summary.overdue_count', 1)->has('charges.data', 1)->where('charges.data.0.status', 'overdue'));
        $this->get(route('admin.financial.index', ['unit_id' => $unit->id, 'status' => 'open']))
            ->assertInertia(fn (Assert $page) => $page->has('charges.data', 1)->where('charges.data.0.status', 'open'));
        $this->get(route('admin.financial.index', ['date_to' => '2026-11-14']))->assertOk();
    }

    public function test_admin_lifecycle_records_history_and_prevents_invalid_transitions(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = Unit::factory()->create();
        $payload = ['unit_id' => $unit->id, 'description' => 'Taxa simulada', 'amount' => '123.45', 'due_date' => '2026-11-15'];
        $this->actingAs($admin)->post(route('admin.financial.store'), $payload)->assertRedirect();
        $charge = FinancialCharge::query()->sole();
        $this->assertSame(12345, $charge->amount_cents);
        $this->put(route('admin.financial.update', $charge), array_replace($payload, ['amount' => '234.50']))->assertRedirect();
        $this->patch(route('admin.financial.change', $charge), ['action' => 'pay', 'paid_at' => '2026-11-14'])->assertRedirect();
        $this->assertSame('paid', $charge->fresh()->status);
        $this->put(route('admin.financial.update', $charge), $payload)->assertSessionHasErrors('charge');
        $this->patch(route('admin.financial.change', $charge), ['action' => 'cancel', 'reason' => 'Teste'])->assertSessionHasErrors('charge');
        $this->patch(route('admin.financial.change', $charge), ['action' => 'reopen'])->assertRedirect();
        $this->patch(route('admin.financial.change', $charge), ['action' => 'cancel', 'reason' => 'Duplicada'])->assertRedirect();
        $this->assertSame('cancelled', $charge->fresh()->status);
        $this->assertSame(['cancel', 'reopen', 'pay', 'updated', 'created'], $charge->history()->pluck('action')->all());
        $this->get(route('admin.financial.show', $charge))->assertInertia(fn (Assert $page) => $page
            ->has('charge.history', 5)->where('charge.history.0.reason', 'Duplicada')->where('charge.history.1.actor', $admin->name));
    }

    public function test_validation_rejects_invalid_values_and_unauthorized_users(): void
    {
        $url = route('admin.financial.store');
        $this->get(route('admin.financial.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->porteiro()->create())->get(route('admin.financial.index'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post($url, ['unit_id' => 99999, 'description' => '', 'amount' => '0.001', 'due_date' => 'invalid'])
            ->assertSessionHasErrors(['unit_id', 'description', 'amount', 'due_date']);
        $charge = FinancialCharge::factory()->create();
        $this->patch(route('admin.financial.change', $charge), ['action' => 'cancel', 'reason' => ''])->assertSessionHasErrors('reason');
        $this->patch(route('admin.financial.change', $charge), ['action' => 'pay', 'paid_at' => '2026-11-15'])->assertSessionHasErrors('paid_at');
    }

    public function test_resident_downloads_only_open_or_overdue_simulated_boleto(): void
    {
        $resident = User::factory()->morador()->create();
        $charge = FinancialCharge::factory()->create(['unit_id' => $resident->unit_id, 'due_date' => '2026-11-01', 'amount_cents' => 12345]);
        $response = $this->actingAs($resident)->get(route('morador.financial.boleto', $charge))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString(iconv('UTF-8', 'Windows-1252', 'BOLETO SIMULADO - NÃO VÁLIDO PARA PAGAMENTO'), $response->getContent());
        $this->assertStringContainsString('123,45', $response->getContent());
        $charge->update(['status' => 'paid', 'paid_at' => today()]);
        $this->get(route('morador.financial.boleto', $charge))->assertNotFound();
        $charge->update(['status' => 'cancelled']);
        $this->get(route('morador.financial.boleto', $charge))->assertNotFound();
    }

    public function test_demo_seeder_is_idempotent_and_records_creation_history(): void
    {
        User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $this->seed(FinancialChargeSeeder::class);
        $this->seed(FinancialChargeSeeder::class);
        $this->assertSame(2, FinancialCharge::where('unit_id', $resident->unit_id)->count());
        $this->assertSame(2, FinancialChargeHistory::count());
    }
}
