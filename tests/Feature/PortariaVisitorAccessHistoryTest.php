<?php

namespace Tests\Feature;

use App\Enums\VisitorAccessStatus;
use App\Models\Unit;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use App\Services\VisitorAccessQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortariaVisitorAccessHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_doorman_receives_paginated_history_with_real_situations_and_sanitized_data(): void
    {
        $unit = Unit::factory()->create(['block' => 'A', 'number' => '101']);
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $visitor = Visitor::factory()->create([
            'name' => 'Helena Visitante',
            'cpf' => '529.982.247-25',
            'phone' => '(65) 99999-9999',
        ]);
        $entryDoorman = User::factory()->porteiro()->create(['name' => 'Paulo Entrada']);
        $exitDoorman = User::factory()->porteiro()->create(['name' => 'Rita Saída']);
        $authorization = VisitorAuthorization::factory()->active()->create([
            'visitor_id' => $visitor->id,
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
            'vehicle_plate' => 'ABC1D23',
            'access_code' => 'csa_historico_secreto',
            'invitation_token_hash' => hash('sha256', 'token-historico-secreto'),
        ]);
        $finishedAccess = VisitorAccess::factory()->create([
            'visitor_authorization_id' => $authorization->id,
            'doorman_id' => $entryDoorman->id,
            'exit_doorman_id' => $exitDoorman->id,
            'entry_time' => now()->subHour(),
            'exit_time' => now()->subMinutes(30),
            'validation_status' => VisitorAccessStatus::Validated,
            'observations' => 'Informação interna',
        ]);
        $openAccess = VisitorAccess::factory()->open()->create([
            'entry_time' => now()->subHours(2),
        ]);
        $deniedAccess = VisitorAccess::factory()->rejected()->create([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs(User::factory()->porteiro()->create())
            ->get(route('portaria.visitor-access-history.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('portaria/visitor-access-history/index')
            ->where('timezone', config('app.timezone'))
            ->has('accesses.data', 3)
            ->where('accesses.data.0.visitor_name', 'Helena Visitante')
            ->where('accesses.data.0.unit', ['block' => 'A', 'number' => '101'])
            ->where('accesses.data.0.vehicle_plate', 'ABC1D23')
            ->where('accesses.data.0.entry_time', $finishedAccess->entry_time->toIso8601String())
            ->where('accesses.data.0.exit_time', $finishedAccess->exit_time->toIso8601String())
            ->where('accesses.data.0.entry_doorman_name', 'Paulo Entrada')
            ->where('accesses.data.0.exit_doorman_name', 'Rita Saída')
            ->where('accesses.data.0.situation', 'finished')
            ->where('accesses.data.0.situation_label', 'Finalizado')
            ->where('accesses.data.1.situation', 'present')
            ->where('accesses.data.2.situation', 'denied')
            ->where('accesses.data.2.entry_time', null)
            ->where('accesses.data.2.exit_time', null)
            ->missing('accesses.data.0.id')
            ->missing('accesses.data.0.cpf')
            ->missing('accesses.data.0.phone')
            ->missing('accesses.data.0.access_code')
            ->missing('accesses.data.0.invitation_token_hash')
            ->missing('accesses.data.0.observations')
            ->missing('accesses.data.0.visitor_authorization_id')
            ->missing('accesses.data.0.doorman_id')
            ->missing('accesses.data.0.exit_doorman_id'));

        $serializedResponse = $response->getContent();

        $this->assertStringNotContainsString($visitor->cpf, $serializedResponse);
        $this->assertStringNotContainsString($visitor->phone, $serializedResponse);
        $this->assertStringNotContainsString($authorization->access_code, $serializedResponse);
        $this->assertStringNotContainsString($authorization->invitation_token_hash, $serializedResponse);
        $this->assertStringNotContainsString('Informação interna', $serializedResponse);
        $this->assertModelExists($openAccess);
        $this->assertModelExists($deniedAccess);
    }

    public function test_history_filters_are_combined_on_the_backend(): void
    {
        $matchingUnit = Unit::factory()->create(['block' => 'B', 'number' => '202']);
        $otherUnit = Unit::factory()->create(['block' => 'C', 'number' => '303']);
        $matchingAccess = $this->createAccess([
            'unit' => $matchingUnit,
            'visitor_name' => 'Marina Filtro',
            'entry_time' => '2026-08-20 14:00:00',
            'exit_time' => '2026-08-20 15:00:00',
        ]);
        $this->createAccess([
            'unit' => $matchingUnit,
            'visitor_name' => 'Marina Aberta',
            'entry_time' => '2026-08-20 16:00:00',
            'exit_time' => null,
        ]);
        $this->createAccess([
            'unit' => $otherUnit,
            'visitor_name' => 'Marina Outra Unidade',
            'entry_time' => '2026-08-20 14:30:00',
            'exit_time' => '2026-08-20 15:30:00',
        ]);
        $this->createAccess([
            'unit' => $matchingUnit,
            'visitor_name' => 'Carlos Fora do Período',
            'entry_time' => '2026-08-21 14:00:00',
            'exit_time' => '2026-08-21 15:00:00',
        ]);

        $this->actingAs(User::factory()->porteiro()->create())
            ->get(route('portaria.visitor-access-history.index', [
                'search' => 'Marina Filtro',
                'unit_id' => $matchingUnit->id,
                'situation' => 'finished',
                'date_from' => '2026-08-20',
                'date_to' => '2026-08-20',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('accesses.data', 1)
                ->where('accesses.data.0.visitor_name', 'Marina Filtro')
                ->where('accesses.data.0.entry_time', $matchingAccess->entry_time->toIso8601String())
                ->where('filters.search', 'Marina Filtro')
                ->where('filters.unit_id', $matchingUnit->id)
                ->where('filters.situation', 'finished')
                ->where('filters.date_from', '2026-08-20')
                ->where('filters.date_to', '2026-08-20'));
    }

    public function test_date_filter_includes_persisted_denials_using_their_recorded_time(): void
    {
        $deniedAccess = VisitorAccess::factory()->rejected()->create([
            'created_at' => '2026-08-25 09:00:00',
            'updated_at' => '2026-08-25 09:00:00',
        ]);
        VisitorAccess::factory()->rejected()->create([
            'created_at' => '2026-08-26 09:00:00',
            'updated_at' => '2026-08-26 09:00:00',
        ]);

        $this->actingAs(User::factory()->porteiro()->create())
            ->get(route('portaria.visitor-access-history.index', [
                'situation' => 'denied',
                'date_from' => '2026-08-25',
                'date_to' => '2026-08-25',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('accesses.data', 1)
                ->where('accesses.data.0.situation', 'denied')
                ->where(
                    'accesses.data.0.visitor_name',
                    $deniedAccess->visitorAuthorization->visitor->name,
                ));
    }

    public function test_history_is_paginated_in_descending_entry_order_and_preserves_filters(): void
    {
        foreach (range(1, 11) as $position) {
            $this->createAccess([
                'visitor_name' => 'Paginado '.$position,
                'entry_time' => now()->subMinutes($position),
                'exit_time' => null,
            ]);
        }

        $this->actingAs(User::factory()->porteiro()->create())
            ->get(route('portaria.visitor-access-history.index', [
                'search' => 'Paginado',
                'situation' => 'present',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('accesses.data', 10)
                ->where('accesses.data.0.visitor_name', 'Paginado 1')
                ->where('accesses.last_page', 2)
                ->where(
                    'accesses.next_page_url',
                    fn (?string $url): bool => str_contains((string) $url, 'search=Paginado')
                        && str_contains((string) $url, 'situation=present'),
                ));
    }

    public function test_history_rejects_invalid_filters(): void
    {
        $doorman = User::factory()->porteiro()->create();
        $route = route('portaria.visitor-access-history.index');

        $this->actingAs($doorman)
            ->from($route)
            ->get(route('portaria.visitor-access-history.index', [
                'date_from' => '2026-08-21',
                'date_to' => '2026-08-20',
            ]))
            ->assertRedirect($route)
            ->assertSessionHasErrors('date_to');

        $this->actingAs($doorman)
            ->from($route)
            ->get(route('portaria.visitor-access-history.index', ['situation' => 'invented']))
            ->assertRedirect($route)
            ->assertSessionHasErrors('situation');
    }

    public function test_only_active_verified_doormen_can_access_history(): void
    {
        $route = route('portaria.visitor-access-history.index');

        $this->get($route)->assertRedirect(route('login'));

        foreach ([
            User::factory()->admin()->create(),
            User::factory()->morador()->create(),
        ] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)
                ->get($route)
                ->assertForbidden();
        }

        $this->actingAs(User::factory()->porteiro()->inactive()->create())
            ->get($route)
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $this->actingAs(User::factory()->porteiro()->unverified()->create())
            ->get($route)
            ->assertRedirect(route('verification.notice'));

        $this->actingAs(User::factory()->porteiro()->create())
            ->get($route)
            ->assertInertia(fn (Assert $page) => $page
                ->component('portaria/visitor-access-history/index'));
    }

    public function test_history_query_does_not_introduce_n_plus_one_queries(): void
    {
        $this->createAccess([
            'entry_time' => now()->subHour(),
            'exit_time' => now()->subMinutes(30),
        ]);
        $service = app(VisitorAccessQueryService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $service->paginateHistoryForPortaria([]);
            $singleAccessQueryCount = count(DB::getQueryLog());

            foreach (range(1, 9) as $position) {
                $this->createAccess([
                    'entry_time' => now()->subHours($position + 1),
                    'exit_time' => now()->subHours($position),
                ]);
            }

            DB::flushQueryLog();
            $service->paginateHistoryForPortaria([]);
            $multipleAccessQueryCount = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }

        $this->assertSame($singleAccessQueryCount, $multipleAccessQueryCount);
        $this->assertLessThanOrEqual(7, $multipleAccessQueryCount);
    }

    /**
     * @param  array{unit?: Unit, visitor_name?: string, entry_time?: \DateTimeInterface|string|null, exit_time?: \DateTimeInterface|string|null}  $attributes
     */
    private function createAccess(array $attributes = []): VisitorAccess
    {
        $unit = $attributes['unit'] ?? Unit::factory()->create();
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $visitor = Visitor::factory()->create([
            'name' => $attributes['visitor_name'] ?? fake()->name(),
        ]);
        $authorization = VisitorAuthorization::factory()->active()->create([
            'visitor_id' => $visitor->id,
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
        ]);
        $exitTime = $attributes['exit_time'] ?? null;

        return VisitorAccess::factory()->create([
            'visitor_authorization_id' => $authorization->id,
            'doorman_id' => User::factory()->porteiro(),
            'exit_doorman_id' => $exitTime === null ? null : User::factory()->porteiro(),
            'entry_time' => $attributes['entry_time'] ?? now()->subHour(),
            'exit_time' => $exitTime,
            'validation_status' => VisitorAccessStatus::Validated,
        ]);
    }
}
