<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReservationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-28 12:00:00'));
    }

    #[DataProvider('transitions')]
    public function test_http_transition_matrix_and_immutable_fields(string $operation, ReservationStatus $from, bool $valid, ReservationStatus $target): void
    {
        $reservation = $this->reservation(['status' => $from]);
        $original = $reservation->refresh()->getRawOriginal();
        $actor = $operation === 'residentCancel' ? $reservation->user : User::factory()->admin()->create();
        $response = $this->actingAs($actor)->patchJson($this->url($operation, $reservation), [
            'status' => 'pending', 'common_area_id' => 999999, 'user_id' => 999999, 'resident_id' => 999999,
            'unit_id' => 999999, 'starts_at' => '2030-01-01 01:00', 'ends_at' => '2030-01-02 02:00',
            'rejection_reason' => '  Motivo fornecido pelo administrador.  ',
        ]);
        if ($valid) {
            $response->assertOk()->assertJsonStructure(['message']);
            $this->assertSame($target, $reservation->refresh()->status);
            $this->assertSame($operation === 'reject' ? 'Motivo fornecido pelo administrador.' : null, $reservation->rejection_reason);
            $after = $reservation->getRawOriginal();
            foreach (['status', 'rejection_reason', 'updated_at'] as $field) {
                unset($original[$field], $after[$field]);
            }
            $this->assertSame($original, $after);
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors('reservation');
            $this->assertSame($original, $reservation->refresh()->getRawOriginal());
        }
        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseCount('notifications', 0);
    }

    public static function transitions(): array
    {
        $cases = [];
        foreach (['approve' => ReservationStatus::Approved, 'reject' => ReservationStatus::Rejected,
            'adminCancel' => ReservationStatus::Cancelled, 'residentCancel' => ReservationStatus::Cancelled] as $operation => $target) {
            foreach (ReservationStatus::cases() as $from) {
                $valid = $from === ReservationStatus::Pending || ($target === ReservationStatus::Cancelled && $from === ReservationStatus::Approved);
                $cases[$operation.' from '.$from->name] = [$operation, $from, $valid, $target];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidReasons')]
    public function test_rejection_requires_a_valid_reason(mixed $reason): void
    {
        $reservation = $this->reservation();
        $this->actingAs(User::factory()->admin()->create())
            ->patchJson($this->url('reject', $reservation), ['rejection_reason' => $reason])
            ->assertUnprocessable()->assertJsonValidationErrors('rejection_reason');
        $this->assertSame(ReservationStatus::Pending, $reservation->refresh()->status);
        $this->assertNull($reservation->rejection_reason);
    }

    public static function invalidReasons(): array
    {
        return [[null], [''], ['   '], [str_repeat('a', 256)], [['invalid']]];
    }

    public function test_reason_limit_and_direct_service_validation(): void
    {
        $admin = User::factory()->admin()->create();
        $reservation = $this->reservation();
        foreach (['   ', str_repeat('a', 256)] as $reason) {
            try {
                app(ReservationService::class)->reject($admin, $reservation, $reason);
                $this->fail('Expected reason validation.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('rejection_reason', $exception->errors());
            }
            $this->assertSame(ReservationStatus::Pending, $reservation->refresh()->status);
        }
        $reason = str_repeat('á', 255);
        $this->actingAs($admin)->patchJson($this->url('reject', $reservation), ['rejection_reason' => ' '.$reason.' '])->assertOk();
        $this->assertSame($reason, $reservation->refresh()->rejection_reason);
    }

    #[DataProvider('cancelTimes')]
    public function test_resident_must_cancel_before_start_but_admin_can_cancel_after_start(string $start, string $end, bool $residentAllowed): void
    {
        $reservation = $this->reservation(['starts_at' => $start, 'ends_at' => $end, 'status' => ReservationStatus::Approved]);
        $this->actingAs($reservation->user);
        $response = $this->patchJson($this->url('residentCancel', $reservation));
        if ($residentAllowed) {
            $response->assertOk();
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors('reservation');
            $this->assertSame(ReservationStatus::Approved, $reservation->refresh()->status);
            $this->actingAs(User::factory()->admin()->create())->patchJson($this->url('adminCancel', $reservation))->assertOk();
        }
        $this->assertSame(ReservationStatus::Cancelled, $reservation->refresh()->status);
        $this->assertDatabaseCount('reservations', 1);
    }

    public static function cancelTimes(): array
    {
        return [
            ['2026-09-28 12:00:01', '2026-09-28 14:00', true],
            ['2026-09-28 12:00:00', '2026-09-28 14:00', false],
            ['2026-09-28 11:00:00', '2026-09-28 14:00', false],
            ['2026-09-27 11:00:00', '2026-09-27 14:00', false],
        ];
    }

    public function test_resident_cannot_cancel_another_residents_reservation_even_in_same_unit(): void
    {
        $reservation = $this->reservation();
        $other = User::factory()->morador()->create(['unit_id' => $reservation->unit_id]);
        $original = $reservation->refresh()->getRawOriginal();
        $this->actingAs($other)->patchJson($this->url('residentCancel', $reservation), [
            'user_id' => $reservation->user_id, 'unit_id' => $reservation->unit_id, 'status' => 'cancelled',
        ])->assertForbidden();
        $this->assertSame($original, $reservation->refresh()->getRawOriginal());
    }

    public function test_owner_can_cancel_after_unit_link_changes(): void
    {
        $reservation = $this->reservation();
        $owner = $reservation->user;
        $owner->update(['unit_id' => null]);
        $this->actingAs($owner)->patchJson($this->url('residentCancel', $reservation))->assertOk();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->refresh()->status);
    }

    #[DataProvider('approvalChanges')]
    public function test_approval_revalidates_area_and_schedule(array $areaChanges, array $reservationChanges): void
    {
        $reservation = $this->reservation($reservationChanges);
        $reservation->commonArea->update($areaChanges);
        $original = $reservation->refresh()->getRawOriginal();
        $this->actingAs(User::factory()->admin()->create())->patchJson($this->url('approve', $reservation))->assertUnprocessable();
        $this->assertSame($original, $reservation->refresh()->getRawOriginal());
    }

    public static function approvalChanges(): array
    {
        return [
            [['status' => 'inactive'], []], [['status' => 'maintenance'], []],
            [['available_from' => '15:00'], []], [['available_until' => '15:00'], []],
            [['max_reservation_minutes' => 60], []],
            [[], ['ends_at' => '2026-09-29 14:00']], [[], ['ends_at' => '2026-09-29 13:00']],
            [[], ['ends_at' => '2026-09-30 01:00']],
            [[], ['starts_at' => '2026-09-27 14:00', 'ends_at' => '2026-09-27 16:00']],
        ];
    }

    #[DataProvider('conflictStates')]
    public function test_approval_excludes_only_itself_and_rechecks_other_conflicts(ReservationStatus $status, bool $conflict): void
    {
        $reservation = $this->reservation();
        $this->reservation(['common_area_id' => $reservation->common_area_id, 'status' => $status]);
        $response = $this->actingAs(User::factory()->admin()->create())->patchJson($this->url('approve', $reservation));
        if ($conflict) {
            $response->assertUnprocessable()->assertJsonValidationErrors('reservation');
        } else {
            $response->assertOk();
        }
        $this->assertSame($conflict ? ReservationStatus::Pending : ReservationStatus::Approved, $reservation->refresh()->status);
        $this->assertDatabaseCount('reservations', 2);
    }

    public static function conflictStates(): array
    {
        return [[ReservationStatus::Pending, true], [ReservationStatus::Approved, true],
            [ReservationStatus::Rejected, false], [ReservationStatus::Cancelled, false], [ReservationStatus::Completed, false]];
    }

    public function test_approval_allows_consecutive_reservations(): void
    {
        $reservation = $this->reservation();
        $this->reservation(['common_area_id' => $reservation->common_area_id, 'starts_at' => '2026-09-29 12:00', 'ends_at' => '2026-09-29 14:00']);
        $this->reservation(['common_area_id' => $reservation->common_area_id, 'starts_at' => '2026-09-29 16:00', 'ends_at' => '2026-09-29 18:00']);
        $this->actingAs(User::factory()->admin()->create())->patchJson($this->url('approve', $reservation))->assertOk();
        $this->assertDatabaseCount('reservations', 3);
    }

    #[DataProvider('availabilityTransitions')]
    public function test_transitions_update_availability(string $operation, int $occupied): void
    {
        $reservation = $this->reservation();
        $url = route('morador.common-areas.availability', ['commonArea' => $reservation->common_area_id, 'date' => '2026-09-29']);
        $this->actingAs($reservation->user)->getJson($url)->assertJsonCount(1, 'occupied_periods');
        $this->actingAs($operation === 'residentCancel' ? $reservation->user : User::factory()->admin()->create())
            ->patchJson($this->url($operation, $reservation), ['rejection_reason' => 'Motivo'])->assertOk();
        $this->actingAs($reservation->user)->getJson($url)->assertOk()->assertJsonCount($occupied, 'occupied_periods');
    }

    public static function availabilityTransitions(): array
    {
        return [['approve', 1], ['reject', 0], ['adminCancel', 0], ['residentCancel', 0]];
    }

    #[DataProvider('blockedActors')]
    public function test_lists_and_operations_are_protected(string $scope, string $kind, string $expected): void
    {
        $reservation = $this->reservation();
        $operations = $scope === 'admin' ? ['approve', 'reject', 'adminCancel'] : ['residentCancel'];
        $requests = [['get', route($scope.'.reservations.index')]];
        foreach ($operations as $operation) {
            $requests[] = ['patch', $this->url($operation, $reservation)];
        }
        foreach ($requests as [$method, $url]) {
            $actor = match ($kind) {
                'admin' => User::factory()->admin()->create(),
                'morador' => $reservation->user,
                'porteiro' => User::factory()->porteiro()->create(),
                'inactive' => User::factory()->create(['role' => $scope === 'admin' ? UserRole::Admin : UserRole::Morador, 'is_active' => false]),
                'unverified' => User::factory()->unverified()->create(['role' => $scope === 'admin' ? UserRole::Admin : UserRole::Morador]),
                default => null,
            };
            if ($actor !== null) {
                $this->actingAs($actor);
            }
            $response = $method === 'get' ? $this->get($url) : $this->patch($url, ['rejection_reason' => 'Motivo']);
            if ($expected === 'forbidden') {
                $response->assertForbidden();
            } else {
                $response->assertRedirect(route($expected));
            }
        }
        $this->assertSame(ReservationStatus::Pending, $reservation->refresh()->status);
    }

    public static function blockedActors(): array
    {
        return [
            ['admin', 'guest', 'login'], ['admin', 'morador', 'forbidden'], ['admin', 'porteiro', 'forbidden'],
            ['admin', 'inactive', 'login'], ['admin', 'unverified', 'verification.notice'],
            ['morador', 'guest', 'login'], ['morador', 'admin', 'forbidden'], ['morador', 'porteiro', 'forbidden'],
            ['morador', 'inactive', 'login'], ['morador', 'unverified', 'verification.notice'],
        ];
    }

    public function test_unknown_reservations_return_not_found(): void
    {
        foreach (['approve', 'reject', 'adminCancel', 'residentCancel'] as $operation) {
            $actor = $operation === 'residentCancel' ? User::factory()->morador()->create() : User::factory()->admin()->create();
            $this->actingAs($actor)->patchJson($this->url($operation, 99999), ['rejection_reason' => 'Motivo'])->assertNotFound();
        }
    }

    public function test_stale_instances_cannot_overwrite_terminal_state(): void
    {
        $service = app(ReservationService::class);
        $admin = User::factory()->admin()->create();
        $reservation = $this->reservation();
        $stale = $reservation->fresh();
        $service->reject($admin, $reservation, 'Motivo');
        foreach (['approve', 'cancelByAdmin'] as $operation) {
            try {
                $service->{$operation}($admin, $stale);
                $this->fail('A stale instance must not overwrite rejection.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('reservation', $exception->errors());
            }
        }
        $this->assertSame(ReservationStatus::Rejected, $reservation->refresh()->status);
        $this->assertSame('Motivo', $reservation->rejection_reason);
    }

    public function test_approval_then_resident_cancellation_uses_fresh_state(): void
    {
        $reservation = $this->reservation();
        $service = app(ReservationService::class);
        $admin = User::factory()->admin()->create();
        $service->approve($admin, $reservation);
        $service->cancelByResident($reservation->user, $reservation);
        $this->assertSame(ReservationStatus::Cancelled, $reservation->refresh()->status);
    }

    public function test_resident_cancellation_cannot_be_overwritten_by_stale_approval(): void
    {
        $reservation = $this->reservation();
        $service = app(ReservationService::class);
        $service->cancelByResident($reservation->user, $reservation);
        try {
            $service->approve(User::factory()->admin()->create(), $reservation);
            $this->fail('A cancelled reservation cannot be approved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reservation', $exception->errors());
        }
        $this->assertSame(ReservationStatus::Cancelled, $reservation->refresh()->status);
    }

    public function test_automatically_approved_reservation_cannot_be_approved_again(): void
    {
        $resident = User::factory()->morador()->create();
        $area = CommonArea::factory()->create(['requires_approval' => false]);
        $reservation = app(ReservationService::class)->create($resident, [
            'common_area_id' => $area->id, 'starts_at' => '2026-09-29 14:00', 'ends_at' => '2026-09-29 16:00',
        ]);
        $this->actingAs(User::factory()->admin()->create())->patchJson($this->url('approve', $reservation))
            ->assertUnprocessable()->assertJsonValidationErrors('reservation');
        $this->assertSame(ReservationStatus::Approved, $reservation->refresh()->status);
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_service_rechecks_actor_and_owner_in_database(): void
    {
        $service = app(ReservationService::class);
        $admin = User::factory()->admin()->create();
        $reservation = $this->reservation();
        User::whereKey($admin->id)->update(['is_active' => false]);
        try {
            $service->approve($admin, $reservation);
            $this->fail('Stale actor must be revalidated.');
        } catch (AuthorizationException) {
            $this->assertSame(ReservationStatus::Pending, $reservation->refresh()->status);
        }
        $other = User::factory()->morador()->create();
        $reservation->user_id = $other->id;
        try {
            $service->cancelByResident($other, $reservation);
            $this->fail('In-memory ownership must not be trusted.');
        } catch (AuthorizationException) {
            $this->assertSame(ReservationStatus::Pending, $reservation->refresh()->status);
        }
    }

    public function test_admin_listing_is_paginated_recent_first_and_sanitized(): void
    {
        $approved = $this->reservation(['status' => ReservationStatus::Approved]);
        $pending = $this->reservation();
        foreach ([ReservationStatus::Rejected, ReservationStatus::Cancelled, ReservationStatus::Completed] as $status) {
            $this->reservation(['status' => $status]);
        }
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.reservations.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/reservations')
            ->has('reservations.data', 5)->where('reservations.data.3', [
                'id' => $pending->id, 'area' => $pending->commonArea->name,
                'resident' => $pending->user->name, 'unit' => $pending->unit->only(['block', 'number']),
                'date' => '2026-09-29', 'start' => '14:00:00', 'end' => '16:00:00',
                'status' => 'pending', 'status_label' => 'Pendente', 'can_cancel' => true,
            ])->where('reservations.data.4.id', $approved->id));
        for ($i = 0; $i < 14; $i++) {
            $this->reservation();
        }
        $this->get(route('admin.reservations.index'))->assertInertia(fn (Assert $page) => $page
            ->has('reservations.data', 15)->where('reservations.total', 19)->where('reservations.last_page', 2));
        $this->get(route('admin.reservations.index', ['page' => 2]))->assertInertia(fn (Assert $page) => $page->has('reservations.data', 4));
    }

    public function test_resident_listing_contains_own_past_and_current_reservations(): void
    {
        $owner = User::factory()->morador()->create();
        $this->actingAs($owner)->get(route('morador.reservations.index'))->assertInertia(fn (Assert $page) => $page->has('reservations.data', 0));
        $own = $this->reservation(['user_id' => $owner->id, 'unit_id' => $owner->unit_id]);
        $started = $this->reservation(['user_id' => $owner->id, 'unit_id' => $owner->unit_id, 'starts_at' => '2026-09-28 11:00', 'ends_at' => '2026-09-28 13:00']);
        $this->reservation(['unit_id' => $owner->unit_id]);
        $this->reservation(['user_id' => $owner->id, 'starts_at' => '2026-09-27 14:00', 'ends_at' => '2026-09-27 16:00']);
        $this->reservation(['user_id' => $owner->id, 'status' => ReservationStatus::Cancelled]);
        $this->get(route('morador.reservations.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('morador/reservations')
            ->has('reservations.data', 4)->where('reservations.data.2.id', $started->id)->where('reservations.data.2.can_cancel', false)
            ->where('reservations.data.1', [
                'id' => $own->id, 'area' => $own->commonArea->name,
                'date' => '2026-09-29', 'start' => '14:00:00', 'end' => '16:00:00',
                'status' => 'pending', 'status_label' => 'Pendente', 'can_cancel' => true,
            ]));
        $this->patchJson($this->url('residentCancel', $own))->assertOk();
        $this->get(route('morador.reservations.index'))->assertInertia(fn (Assert $page) => $page->has('reservations.data', 4));
    }

    #[DataProvider('availabilityTransitions')]
    public function test_transition_reloads_and_updates_inside_transaction(string $operation, int $occupied): void
    {
        $reservation = $this->reservation();
        $actor = $operation === 'residentCancel' ? $reservation->user : User::factory()->admin()->create();
        $baseline = DB::transactionLevel();
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_contains($query->sql, 'reservations')) {
                $queries[] = [$query->sql, $query->connection->transactionLevel()];
            }
        });
        $service = app(ReservationService::class);
        match ($operation) {
            'approve' => $service->approve($actor, $reservation),
            'reject' => $service->reject($actor, $reservation, 'Motivo'),
            'adminCancel' => $service->cancelByAdmin($actor, $reservation),
            'residentCancel' => $service->cancelByResident($actor, $reservation),
        };
        $this->assertStringContainsString('select', $queries[0][0]);
        $this->assertStringContainsString('update', $queries[array_key_last($queries)][0]);
        foreach ($queries as [, $level]) {
            $this->assertGreaterThan($baseline, $level);
        }
        $this->assertSame($baseline, DB::transactionLevel());
    }

    private function reservation(array $attributes = []): Reservation
    {
        return Reservation::factory()->create(array_replace([
            'starts_at' => '2026-09-29 14:00', 'ends_at' => '2026-09-29 16:00', 'status' => ReservationStatus::Pending,
        ], $attributes));
    }

    private function url(string $operation, Reservation|int $reservation): string
    {
        $route = match ($operation) {
            'approve' => 'admin.reservations.approve', 'reject' => 'admin.reservations.reject',
            'adminCancel' => 'admin.reservations.cancel', 'residentCancel' => 'morador.reservations.cancel',
        };

        return route($route, $reservation);
    }
}
