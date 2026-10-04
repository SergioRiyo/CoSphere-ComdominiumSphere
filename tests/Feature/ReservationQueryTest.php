<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;
use App\Models\User;
use App\Services\ReservationQueryService;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReservationQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-30 10:00:00'));
    }

    #[DataProvider('roles')]
    public function test_all_statuses_and_dates_are_consultable_with_owner_scope(string $role): void
    {
        $resident = User::factory()->morador()->create();
        $other = User::factory()->morador()->create(['unit_id' => $resident->unit_id]);
        $actor = $role === 'admin' ? User::factory()->admin()->create() : $resident;
        $own = [];
        foreach (ReservationStatus::cases() as $status) {
            $own[] = Reservation::factory()->create($this->period('2025-09-01') + ['user_id' => $resident->id, 'unit_id' => $resident->unit_id, 'status' => $status]);
        }
        $foreign = Reservation::factory()->create($this->period() + ['user_id' => $other->id, 'unit_id' => $resident->unit_id]);
        $this->actingAs($actor)->get(route($role.'.reservations.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component($role.'/reservations')->has('reservations.data', $role === 'admin' ? 5 : 4)->has('statuses', 4)
            ->where('reservations.total', $role === 'admin' ? 5 : 4));
        foreach ($own as $reservation) {
            $this->get(route($role.'.reservations.show', $reservation))->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component($role.'/reservation-details')->where('reservation.status', $reservation->status->value)
                ->where('reservation.status_label', $reservation->status->label())->has('reservation.history', 0));
        }
        $response = $this->get(route($role.'.reservations.show', $foreign));
        $role === 'admin' ? $response->assertOk() : $response->assertNotFound();
        $this->assertDatabaseCount('reservation_status_histories', 0);
    }

    public static function roles(): array
    {
        return [['morador'], ['admin']];
    }

    #[DataProvider('filters')]
    public function test_filters_are_combined_in_database_and_include_boundary_days(string $role, array $filters, array $expected): void
    {
        $actor = User::factory()->{$role}()->create();
        $owner = $role === 'morador' ? $actor : User::factory()->morador()->create();
        $ids = [];
        foreach ([['2026-09-01', 'pending'], ['2026-09-15', 'confirmed'], ['2026-09-30', 'confirmed'], ['2026-10-01', 'confirmed'], ['2026-09-30', 'cancelled']] as [$date, $status]) {
            $ids[] = Reservation::factory()->create($this->period($date) + ['status' => $status, 'user_id' => $owner->id, 'unit_id' => $owner->unit_id])->id;
        }
        $this->actingAs($actor)->get(route($role.'.reservations.index', $filters))->assertInertia(fn (Assert $page) => $page
            ->where('reservations.total', count($expected))
            ->where('reservations.data', fn ($rows): bool => $rows->pluck('id')->all() === array_map(fn (int $position): int => $ids[$position], $expected)));
    }

    public static function filters(): array
    {
        $cases = [];
        foreach (['admin', 'morador'] as $role) {
            foreach ([
                [['status' => 'confirmed'], [3, 2, 1]],
                [['date_from' => '2026-09-01', 'date_to' => '2026-09-30'], [4, 2, 1, 0]],
                [['status' => 'confirmed', 'date_from' => '2026-09-15', 'date_to' => '2026-09-30'], [2, 1]],
                [['date_from' => '2026-09-30', 'date_to' => '2026-09-30'], [4, 2]],
                [['date_from' => '2026-10-01'], [3]],
                [['date_to' => '2026-09-01'], [0]],
                [['status' => 'rejected'], []],
            ] as [$filters, $expected]) {
                $cases[] = [$role, $filters, $expected];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_return_controlled_errors(string $role, array $filters, string $field): void
    {
        $this->actingAs(User::factory()->{$role}()->create())->getJson(route($role.'.reservations.index', $filters))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('reservation_status_histories', 0);
    }

    public static function invalidFilters(): array
    {
        $cases = [];
        foreach (['admin', 'morador'] as $role) {
            foreach ([
                [['status' => 'approved'], 'status'], [['status' => 'completed'], 'status'], [['status' => ['pending']], 'status'],
                [['date_from' => 'tomorrow'], 'date_from'], [['date_to' => '2026-02-30'], 'date_to'],
                [['date_from' => '2026-10-01', 'date_to' => '2026-09-01'], 'date_to'],
                [['page' => 0], 'page'], [['page' => 'invalid'], 'page'],
            ] as [$filters, $field]) {
                $cases[] = [$role, $filters, $field];
            }
        }

        return $cases;
    }

    #[DataProvider('roles')]
    public function test_pagination_preserves_only_validated_filters_and_queries_do_not_mutate(string $role): void
    {
        $actor = User::factory()->{$role}()->create();
        $owner = $role === 'morador' ? $actor : User::factory()->morador()->create();
        $reservations = Reservation::factory()->count(17)->create($this->period() + ['status' => ReservationStatus::Cancelled, 'user_id' => $owner->id]);
        $original = $reservations->first()->refresh()->getRawOriginal();
        $filters = ['status' => 'cancelled', 'date_from' => '2026-09-30', 'date_to' => '2026-09-30'];
        $this->actingAs($actor)->get(route($role.'.reservations.index', $filters + ['user_id' => 99999, 'unit_id' => 99999, 'changed_by_user_id' => 99999]))
            ->assertInertia(fn (Assert $page) => $page->has('reservations.data', 15)->where('reservations.total', 17)
                ->where('filters', $filters)
                ->where('reservations.next_page_url', function (string $url) use ($filters): bool {
                    parse_str(parse_url($url, PHP_URL_QUERY), $query);

                    return $query == $filters + ['page' => '2'];
                }));
        $this->get(route($role.'.reservations.index', $filters + ['page' => 2]))->assertInertia(fn (Assert $page) => $page->has('reservations.data', 2));
        $this->get(route($role.'.reservations.show', ['reservation' => $reservations->first()->id, 'status' => 'confirmed', 'user_id' => 99999]))->assertOk();
        $this->assertSame($original, $reservations->first()->refresh()->getRawOriginal());
        $this->assertDatabaseCount('reservation_status_histories', 0);
    }

    public function test_direct_detail_and_history_never_leak_across_residents_even_in_same_unit(): void
    {
        $owner = User::factory()->morador()->create();
        $other = User::factory()->morador()->create(['unit_id' => $owner->unit_id]);
        $reservation = Reservation::factory()->create($this->period() + ['user_id' => $owner->id, 'unit_id' => $owner->unit_id]);
        ReservationStatusHistory::factory()->create(['reservation_id' => $reservation->id, 'reason' => 'Motivo privado']);
        $this->actingAs($other)->getJson(route('morador.reservations.show', $reservation))->assertNotFound()->assertDontSee('Motivo privado');
        $this->getJson(route('morador.reservations.show', 99999))->assertNotFound();
        $this->get(route('morador.reservations.index', ['user_id' => $owner->id, 'unit_id' => $owner->unit_id, 'admin' => true]))->assertInertia(fn (Assert $page) => $page->has('reservations.data', 0));
        $owner->update(['unit_id' => null]);
        $this->actingAs($owner)->get(route('morador.reservations.show', $reservation))->assertOk();
    }

    #[DataProvider('roles')]
    public function test_detail_serialization_and_chronological_history_privacy(string $role): void
    {
        $owner = User::factory()->morador()->create();
        $admin = User::factory()->admin()->create();
        $reservation = Reservation::factory()->create($this->period() + ['status' => ReservationStatus::Rejected, 'rejection_reason' => 'Motivo final', 'user_id' => $owner->id, 'unit_id' => $owner->unit_id]);
        $later = ReservationStatusHistory::factory()->create(['reservation_id' => $reservation->id, 'changed_by_user_id' => $admin->id, 'actor_role' => UserRole::Admin, 'to_status' => ReservationStatus::Rejected, 'reason' => 'Motivo final', 'created_at' => '2026-09-29 12:00:00']);
        $earlier = ReservationStatusHistory::factory()->create(['reservation_id' => $reservation->id, 'changed_by_user_id' => $owner->id, 'actor_role' => UserRole::Morador, 'from_status' => null, 'to_status' => ReservationStatus::Pending, 'created_at' => '2026-09-28 09:00:00']);
        User::whereKey($admin->id)->update(['role' => UserRole::Porteiro]);
        $actor = $role === 'admin' ? User::factory()->admin()->create() : $owner;
        $this->actingAs($actor)->get(route($role.'.reservations.show', $reservation))->assertInertia(fn (Assert $page) => $page
            ->where('reservation.rejection_reason', 'Motivo final')->where('reservation.status', 'rejected')
            ->where('reservation.can_cancel', false)->has('reservation.history', 2)
            ->where('reservation.history.0.id', $earlier->id)->where('reservation.history.1.id', $later->id)
            ->where('reservation.history.0.actor', $role === 'admin' ? $owner->name.' (Morador)' : 'Você')
            ->where('reservation.history.1.actor', $role === 'admin' ? $admin->name.' (Administrador)' : 'Administrador')
            ->where('reservation.history.1.reason', 'Motivo final')->missing('reservation.user_id')->missing('reservation.unit_id')
            ->missing('reservation.history.1.changed_by_user_id')->missing('reservation.history.1.email')
            ->missing('reservation.history.1.cpf')->missing('reservation.history.1.phone'));
        $data = app(ReservationQueryService::class)->details($actor, $reservation->id);
        $this->assertSame(['id', 'from_status', 'from_label', 'to_status', 'to_label', 'actor', 'reason', 'created_at'], array_keys($data['history'][0]));
        if ($role === 'morador') {
            $this->assertArrayNotHasKey('resident', $data);
            $this->assertArrayNotHasKey('unit', $data);
            $this->assertStringNotContainsString($admin->name, json_encode($data));
        } else {
            $this->assertSame($owner->name, $data['resident']);
            $this->assertSame($reservation->unit->only(['block', 'number']), $data['unit']);
        }
    }

    #[DataProvider('authorization')]
    public function test_index_and_show_authorization(string $scope, string $kind, int $status): void
    {
        $reservation = Reservation::factory()->create();
        foreach (['index', 'show'] as $endpoint) {
            if ($kind !== 'guest') {
                $factory = User::factory()->{$scope}();
                $user = match ($kind) {
                    'inactive' => $factory->inactive()->create(),
                    'unverified' => $factory->unverified()->create(),
                    'other' => User::factory()->{$scope === 'admin' ? 'morador' : 'admin'}()->create(),
                    default => User::factory()->porteiro()->create(),
                };
                $this->actingAs($user);
            }
            $this->getJson(route($scope.'.reservations.'.$endpoint, $endpoint === 'show' ? $reservation->id : []))->assertStatus($status);
        }
    }

    public static function authorization(): array
    {
        $cases = [];
        foreach (['admin', 'morador'] as $scope) {
            foreach (['guest' => 401, 'inactive' => 302, 'unverified' => 403, 'other' => 403, 'porteiro' => 403] as $kind => $status) {
                $cases[] = [$scope, $kind, $status];
            }
        }

        return $cases;
    }

    #[DataProvider('roles')]
    public function test_query_count_stays_constant_as_list_and_timeline_grow(string $role): void
    {
        $actor = User::factory()->{$role}()->create();
        $owner = $role === 'morador' ? $actor : User::factory()->morador()->create();
        $reservation = Reservation::factory()->create($this->period() + ['user_id' => $owner->id]);
        ReservationStatusHistory::factory()->create(['reservation_id' => $reservation->id]);
        $queries = 0;
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_starts_with($query->sql, 'select')) {
                $queries++;
            }
        });
        $service = app(ReservationQueryService::class);
        $service->paginate($actor, []);
        $listCount = $queries;
        $queries = 0;
        $service->details($actor, $reservation->id);
        $detailCount = $queries;
        Reservation::factory()->count(20)->create($this->period() + ['user_id' => $owner->id]);
        ReservationStatusHistory::factory()->count(20)->create(['reservation_id' => $reservation->id]);
        $queries = 0;
        $list = $service->paginate($actor, []);
        $this->assertSame($listCount, $queries);
        $this->assertCount(15, $list->items());
        $queries = 0;
        $service->details($actor, $reservation->id);
        $this->assertSame($detailCount, $queries);
    }

    private function period(string $date = '2026-09-30'): array
    {
        return ['starts_at' => $date.' 14:00', 'ends_at' => $date.' 16:00'];
    }
}
