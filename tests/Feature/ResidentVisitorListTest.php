<?php

namespace Tests\Feature;

use App\Enums\VisitorAuthorizationStatus;
use App\Models\Unit;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorAuthorization;
use App\Services\VisitorAuthorizationQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResidentVisitorListTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_residents_can_access_the_visitor_list(): void
    {
        $route = route('morador.visitors.index');

        $this->get($route)->assertRedirect(route('login'));

        $this->actingAs(User::factory()->admin()->create())
            ->get($route)
            ->assertForbidden();

        $this->actingAs(User::factory()->porteiro()->create())
            ->get($route)
            ->assertForbidden();

        $this->actingAs(User::factory()->morador()->create())
            ->get($route)
            ->assertInertia(fn (Assert $page) => $page->component('morador/visitors/index'));
    }

    public function test_resident_receives_only_authorizations_from_their_unit(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $coResident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $ownAuthorization = $this->createAuthorization($unit, $resident);
        $sharedAuthorization = $this->createAuthorization($unit, $coResident, [
            'start_date' => now()->addHour(),
            'end_date' => now()->addHours(2),
        ]);
        $this->createAuthorization($otherUnit);

        $this->actingAs($resident)
            ->get(route('morador.visitors.index', [
                'unit_id' => $otherUnit->id,
                'resident_id' => User::factory()->morador()->create()->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('authorizations.data', 2)
                ->where('authorizations.data.0.id', $sharedAuthorization->id)
                ->where('authorizations.data.1.id', $ownAuthorization->id));
    }

    public function test_resident_without_unit_receives_an_empty_list(): void
    {
        $resident = User::factory()->morador()->create(['unit_id' => null]);
        $this->createAuthorization(Unit::factory()->create());

        $this->actingAs($resident)
            ->get(route('morador.visitors.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('authorizations.data', 0)
                ->where('authorizations.total', 0));
    }

    public function test_search_filters_and_pagination_are_applied_on_the_backend(): void
    {
        $unit = Unit::factory()->create();
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $visitor = Visitor::factory()->create([
            'name' => 'Maria Pesquisável',
            'cpf' => '529.982.247-25',
        ]);
        $matchingAuthorization = $this->createAuthorization($unit, $resident, [
            'visitor_id' => $visitor->id,
            'status' => VisitorAuthorizationStatus::Canceled,
            'start_date' => '2026-09-15 10:00:00',
            'end_date' => '2026-09-15 12:00:00',
        ]);
        $this->createAuthorization($unit, $resident, [
            'status' => VisitorAuthorizationStatus::Canceled,
            'start_date' => '2026-10-15 10:00:00',
            'end_date' => '2026-10-15 12:00:00',
        ]);

        foreach (['Maria Pesquisável', '529.982.247-25', '52998224725'] as $search) {
            $this->actingAs($resident)
                ->get(route('morador.visitors.index', [
                    'search' => $search,
                    'status' => VisitorAuthorizationStatus::Canceled->value,
                    'date_from' => '2026-09-15',
                    'date_to' => '2026-09-15',
                ]))
                ->assertInertia(fn (Assert $page) => $page
                    ->has('authorizations.data', 1)
                    ->where('authorizations.data.0.id', $matchingAuthorization->id)
                    ->where('filters.search', $search)
                    ->where('filters.status', VisitorAuthorizationStatus::Canceled->value)
                    ->where('filters.date_from', '2026-09-15')
                    ->where('filters.date_to', '2026-09-15'));
        }

        VisitorAuthorization::factory()->active()->count(11)->create([
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
        ]);

        $this->actingAs($resident)
            ->get(route('morador.visitors.index', [
                'status' => VisitorAuthorizationStatus::Active->value,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('authorizations.data', 10)
                ->where('authorizations.last_page', 2)
                ->where(
                    'authorizations.next_page_url',
                    fn (?string $url): bool => str_contains(
                        (string) $url,
                        'status=active',
                    ),
                ));
    }

    public function test_pending_authorization_is_serialized_without_sensitive_credentials(): void
    {
        $unit = Unit::factory()->create();
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $authorization = VisitorAuthorization::factory()->pendingData()->create([
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
        ]);

        $this->actingAs($resident)
            ->get(route('morador.visitors.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('authorizations.data.0.id', $authorization->id)
                ->where('authorizations.data.0.visitor', null)
                ->where('authorizations.data.0.status_label', 'Aguardando dados')
                ->missing('authorizations.data.0.access_code')
                ->missing('authorizations.data.0.invitation_token_hash'));
    }

    public function test_list_masks_visitor_cpf_but_details_show_the_authorized_data(): void
    {
        $unit = Unit::factory()->create();
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $visitor = Visitor::factory()->create(['cpf' => '529.982.247-25']);
        $authorization = $this->createAuthorization($unit, $resident, [
            'visitor_id' => $visitor->id,
        ]);

        $this->actingAs($resident)
            ->get(route('morador.visitors.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('authorizations.data.0.visitor.cpf', '***.***.***-25'));

        $this->actingAs($resident)
            ->get(route('morador.visitors.show', $authorization))
            ->assertInertia(fn (Assert $page) => $page
                ->where('authorization.visitor.cpf', '529.982.247-25'));
    }

    public function test_expired_active_authorization_is_presented_and_filtered_as_expired(): void
    {
        $unit = Unit::factory()->create();
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $authorization = $this->createAuthorization($unit, $resident, [
            'status' => VisitorAuthorizationStatus::Active,
            'start_date' => now()->subDays(2),
            'end_date' => now()->subDay(),
        ]);

        $this->actingAs($resident)
            ->get(route('morador.visitors.index', ['status' => 'expired']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('authorizations.data', 1)
                ->where('authorizations.data.0.id', $authorization->id)
                ->where('authorizations.data.0.status', 'expired')
                ->where('authorizations.data.0.status_label', 'Expirada'));
    }

    public function test_expired_pending_invitation_is_presented_as_expired(): void
    {
        $unit = Unit::factory()->create();
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $authorization = VisitorAuthorization::factory()->pendingData()->create([
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
            'invitation_expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($resident)
            ->get(route('morador.visitors.index', ['status' => 'expired']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('authorizations.data', 1)
                ->where('authorizations.data.0.id', $authorization->id)
                ->where('authorizations.data.0.status', 'expired'));
    }

    public function test_invalid_period_filter_is_rejected(): void
    {
        $resident = User::factory()->morador()->create();

        $this->actingAs($resident)
            ->from(route('morador.visitors.index'))
            ->get(route('morador.visitors.index', [
                'date_from' => '2026-10-02',
                'date_to' => '2026-10-01',
            ]))
            ->assertRedirect(route('morador.visitors.index'))
            ->assertSessionHasErrors('date_to');
    }

    public function test_resident_can_view_details_only_from_their_unit(): void
    {
        $unit = Unit::factory()->create();
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $authorization = $this->createAuthorization($unit, $resident);
        $otherAuthorization = $this->createAuthorization(Unit::factory()->create());

        $this->actingAs($resident)
            ->get(route('morador.visitors.show', $authorization))
            ->assertInertia(fn (Assert $page) => $page
                ->component('morador/visitors/show')
                ->where('authorization.id', $authorization->id)
                ->where('authorization.qr_available', true)
                ->missing('authorization.access_code')
                ->missing('authorization.invitation_token_hash'));

        $this->actingAs($resident)
            ->get(route('morador.visitors.show', $otherAuthorization))
            ->assertForbidden();
    }

    public function test_visitor_listing_does_not_introduce_n_plus_one_queries(): void
    {
        $unit = Unit::factory()->create();
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $this->createAuthorization($unit, $resident);
        $service = app(VisitorAuthorizationQueryService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $service->paginateForResident($resident, []);
        $singleAuthorizationQueryCount = count(DB::getQueryLog());

        VisitorAuthorization::factory()->active()->count(9)->create([
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
        ]);

        DB::flushQueryLog();
        $service->paginateForResident($resident, []);
        $multipleAuthorizationQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($singleAuthorizationQueryCount, $multipleAuthorizationQueryCount);
        $this->assertLessThanOrEqual(4, $multipleAuthorizationQueryCount);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createAuthorization(
        Unit $unit,
        ?User $resident = null,
        array $attributes = [],
    ): VisitorAuthorization {
        $resident ??= User::factory()->morador()->create(['unit_id' => $unit->id]);

        return VisitorAuthorization::factory()->active()->create([
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
            ...$attributes,
        ]);
    }
}
