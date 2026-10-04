<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\Unit;
use App\Models\User;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReservationCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-25 12:00:00'));
    }

    #[DataProvider('approvalModes')]
    public function test_http_creation_uses_authenticated_identity_and_calculated_status(bool $manual, ReservationStatus $status): void
    {
        $resident = User::factory()->morador()->create();
        $other = User::factory()->morador()->create();
        $area = CommonArea::factory()->create(['requires_approval' => $manual]);
        $response = $this->actingAs($resident)->postJson(route('morador.reservations.store'), $this->data($area) + [
            'user_id' => $other->id, 'resident_id' => $other->id, 'unit_id' => $other->unit_id,
            'status' => $manual ? 'confirmed' : 'pending', 'rejection_reason' => 'Injected',
        ])->assertCreated()->assertExactJson([
            'area' => $area->name, 'date' => '2026-09-26', 'start' => '14:00:00', 'end' => '16:00:00',
            'status' => $status->value, 'status_label' => $status->label(),
        ]);
        $response->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseHas('reservations', [
            'common_area_id' => $area->id, 'user_id' => $resident->id, 'unit_id' => $resident->unit_id,
            'status' => $status->value, 'rejection_reason' => null,
        ]);
        $this->getJson($this->availabilityUrl($area))->assertOk()
            ->assertJsonPath('occupied_periods', [['start' => '14:00:00', 'end' => '16:00:00']]);
    }

    public static function approvalModes(): array
    {
        return [[true, ReservationStatus::Pending], [false, ReservationStatus::Approved]];
    }

    #[DataProvider('overlaps')]
    public function test_http_overlap_and_consecutive_boundaries(string $existingEnd, string $start, string $end, bool $conflict): void
    {
        $area = CommonArea::factory()->create();
        $this->occupy($area, ReservationStatus::Approved, '14:00', $existingEnd);
        $this->actingAs(User::factory()->morador()->create());
        $availability = $this->getJson($this->availabilityUrl($area))->assertOk();
        $fits = collect($availability->json('free_periods'))->contains(fn (array $period): bool => $start.':00' >= $period['start'] && $end.':00' <= $period['end']);
        $this->assertSame(! $conflict, $fits);
        $response = $this->postJson(route('morador.reservations.store'), $this->data($area, [
            'starts_at' => '2026-09-26 '.$start, 'ends_at' => '2026-09-26 '.$end,
        ]));
        if ($conflict) {
            $response->assertUnprocessable()->assertJsonValidationErrors('starts_at');
        } else {
            $response->assertCreated();
        }
        $this->assertDatabaseCount('reservations', $conflict ? 1 : 2);
    }

    public static function overlaps(): array
    {
        return [
            'partial before' => ['16:00', '13:00', '15:00', true],
            'partial after' => ['16:00', '15:00', '17:00', true],
            'inside' => ['18:00', '15:00', '16:00', true],
            'contains' => ['16:00', '13:00', '17:00', true],
            'equal' => ['16:00', '14:00', '16:00', true],
            'consecutive before' => ['16:00', '12:00', '14:00', false],
            'consecutive after' => ['16:00', '16:00', '18:00', false],
        ];
    }

    #[DataProvider('statuses')]
    public function test_http_creation_only_conflicts_with_blocking_statuses(ReservationStatus $status, bool $conflict): void
    {
        $area = CommonArea::factory()->create();
        $this->occupy($area, $status);
        $response = $this->actingAs(User::factory()->morador()->create())
            ->postJson(route('morador.reservations.store'), $this->data($area));
        if ($conflict) {
            $response->assertUnprocessable()->assertJsonValidationErrors('starts_at');
        } else {
            $response->assertCreated();
        }
        $this->assertDatabaseCount('reservations', $conflict ? 1 : 2);
    }

    public static function statuses(): array
    {
        return [[ReservationStatus::Pending, true], [ReservationStatus::Approved, true],
            [ReservationStatus::Cancelled, false], [ReservationStatus::Rejected, false]];
    }

    public function test_a_free_calendar_does_not_allow_a_stale_submission_or_duplicate(): void
    {
        $area = CommonArea::factory()->create();
        $first = User::factory()->morador()->create();
        $second = User::factory()->morador()->create();
        $this->actingAs($first)->getJson($this->availabilityUrl($area))->assertJsonCount(0, 'occupied_periods');
        $this->actingAs($second)->postJson(route('morador.reservations.store'), $this->data($area))->assertCreated();
        $this->actingAs($first)->postJson(route('morador.reservations.store'), $this->data($area))
            ->assertUnprocessable()->assertJsonValidationErrors('starts_at');
        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseHas('reservations', ['user_id' => $second->id]);
    }

    #[DataProvider('areaChanges')]
    public function test_area_rules_are_reloaded_after_availability(array $change, string $error): void
    {
        $area = CommonArea::factory()->create();
        $this->actingAs(User::factory()->morador()->create())->getJson($this->availabilityUrl($area))->assertOk();
        $area->update($change);
        $this->postJson(route('morador.reservations.store'), $this->data($area))
            ->assertUnprocessable()->assertJsonValidationErrors($error);
        $this->assertDatabaseCount('reservations', 0);
    }

    public static function areaChanges(): array
    {
        return [[['status' => 'inactive'], 'common_area_id'], [['status' => 'maintenance'], 'common_area_id'],
            [['available_from' => '15:00'], 'starts_at'], [['available_until' => '15:00'], 'ends_at'],
            [['max_reservation_minutes' => 60], 'ends_at']];
    }

    public function test_approval_mode_is_reloaded_at_creation(): void
    {
        $area = CommonArea::factory()->create(['requires_approval' => false]);
        $this->actingAs(User::factory()->morador()->create())->getJson($this->availabilityUrl($area))->assertOk();
        $area->update(['requires_approval' => true]);
        $this->postJson(route('morador.reservations.store'), $this->data($area))->assertCreated()->assertJsonPath('status', 'pending');
    }

    #[DataProvider('invalidInput')]
    public function test_http_invalid_input_never_inserts(string $field, mixed $value): void
    {
        $area = CommonArea::factory()->create();
        $this->actingAs(User::factory()->morador()->create())
            ->postJson(route('morador.reservations.store'), $this->data($area, [$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('reservations', 0);
    }

    public static function invalidInput(): array
    {
        return [
            ['common_area_id', null], ['common_area_id', 999999], ['common_area_id', 'invalid'],
            ['common_area_id', [[1]]], ['ends_at', ['2026-09-26']],
            ['starts_at', null], ['ends_at', null], ['starts_at', 'tomorrow'], ['starts_at', ['2026-09-26']],
            ['starts_at', '2026-02-30 14:00'], ['ends_at', '2026-09-26 25:00'],
            ['ends_at', '2026-09-26 14:00'], ['ends_at', '2026-09-26 13:00'],
            ['ends_at', '2026-09-27 01:00'], ['starts_at', '2026-09-26 14:00Z'],
        ];
    }

    #[DataProvider('scheduleLimits')]
    public function test_http_schedule_limits(string $start, string $end, bool $valid): void
    {
        $area = CommonArea::factory()->create(['max_reservation_minutes' => 120]);
        $response = $this->actingAs(User::factory()->morador()->create())
            ->postJson(route('morador.reservations.store'), $this->data($area, [
                'starts_at' => '2026-09-26 '.$start, 'ends_at' => '2026-09-26 '.$end,
            ]));
        if ($valid) {
            $response->assertCreated();
        } else {
            $response->assertUnprocessable();
        }
        $this->assertDatabaseCount('reservations', $valid ? 1 : 0);
    }

    public static function scheduleLimits(): array
    {
        return [['07:00', '09:00', false], ['21:00', '23:00', false], ['08:00', '10:00', true],
            ['20:00', '22:00', true], ['10:00', '11:30', true], ['10:00', '12:00:01', false]];
    }

    #[DataProvider('nullableLimits')]
    public function test_each_null_schedule_boundary_is_unrestricted(?string $opening, ?string $closing, string $start, string $end): void
    {
        $area = CommonArea::factory()->create(['available_from' => $opening, 'available_until' => $closing]);
        $this->actingAs(User::factory()->morador()->create())
            ->postJson(route('morador.reservations.store'), $this->data($area, [
                'starts_at' => '2026-09-26 '.$start, 'ends_at' => '2026-09-26 '.$end,
            ]))->assertCreated();
        $this->assertDatabaseCount('reservations', 1);
    }

    public static function nullableLimits(): array
    {
        return [[null, '22:00', '00:00', '01:00'], ['08:00', null, '23:00', '23:59:59'], [null, null, '01:00', '02:00']];
    }

    #[DataProvider('pastPeriods')]
    public function test_only_ended_periods_are_rejected(string $start, string $end, bool $valid): void
    {
        $area = CommonArea::factory()->create();
        $this->actingAs(User::factory()->morador()->create())->getJson(route('morador.common-areas.availability', [
            'commonArea' => $area->id, 'date' => '2026-09-25',
        ]))->assertOk();
        $response = $this->postJson(route('morador.reservations.store'), $this->data($area, [
            'starts_at' => $start, 'ends_at' => $end,
        ]));
        if ($valid) {
            $response->assertCreated();
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors('ends_at');
        }
        $this->assertDatabaseCount('reservations', $valid ? 1 : 0);
    }

    public static function pastPeriods(): array
    {
        return [
            ['2026-09-24 10:00', '2026-09-24 11:00', false],
            ['2026-09-25 10:00', '2026-09-25 11:59:59', false],
            ['2026-09-25 10:00', '2026-09-25 12:00', false],
            ['2026-09-25 11:00', '2026-09-25 12:00:01', true],
            ['2026-09-25 12:00', '2026-09-25 13:00', true],
        ];
    }

    public function test_resident_without_unit_gets_controlled_http_error(): void
    {
        $resident = User::factory()->morador()->create(['unit_id' => null]);
        $area = CommonArea::factory()->create();
        $this->actingAs($resident)->postJson(route('morador.reservations.store'), $this->data($area))
            ->assertUnprocessable()->assertJsonValidationErrors('reservation');
        $this->assertDatabaseCount('reservations', 0);
    }

    #[DataProvider('blockedUsers')]
    public function test_route_is_protected(string $actor, string $expected): void
    {
        $user = match ($actor) {
            'admin' => User::factory()->admin()->create(),
            'porteiro' => User::factory()->porteiro()->create(),
            'inactive' => User::factory()->morador()->inactive()->create(),
            'unverified' => User::factory()->morador()->unverified()->create(),
            default => null,
        };
        if ($user !== null) {
            $this->actingAs($user);
        }
        $response = $this->post(route('morador.reservations.store'), $this->data(CommonArea::factory()->create()));
        if ($expected === 'forbidden') {
            $response->assertForbidden();
        } else {
            $response->assertRedirect(route($expected));
        }
        $this->assertDatabaseCount('reservations', 0);
    }

    public static function blockedUsers(): array
    {
        return [['guest', 'login'], ['admin', 'forbidden'], ['porteiro', 'forbidden'],
            ['inactive', 'login'], ['unverified', 'verification.notice']];
    }

    #[DataProvider('invalidResidents')]
    public function test_service_rechecks_persisted_resident_even_without_http(string $change): void
    {
        $resident = User::factory()->morador()->create();
        $area = CommonArea::factory()->create();
        match ($change) {
            'admin' => User::whereKey($resident->id)->update(['role' => UserRole::Admin]),
            'porteiro' => User::whereKey($resident->id)->update(['role' => UserRole::Porteiro]),
            'inactive' => User::whereKey($resident->id)->update(['is_active' => false]),
            'unverified' => User::whereKey($resident->id)->update(['email_verified_at' => null]),
            'no unit' => User::whereKey($resident->id)->update(['unit_id' => null]),
            'deleted' => User::whereKey($resident->id)->delete(),
            'unsaved' => $resident = User::factory()->make(),
        };
        try {
            app(ReservationService::class)->create($resident, $this->data($area));
            $this->fail('Expected resident validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reservation', $exception->errors());
        }
        $this->assertDatabaseCount('reservations', 0);
    }

    public static function invalidResidents(): array
    {
        return [['admin'], ['porteiro'], ['inactive'], ['unverified'], ['no unit'], ['deleted'], ['unsaved']];
    }

    public function test_service_uses_current_unit_and_ignores_payload_identity(): void
    {
        $resident = User::factory()->morador()->create();
        $resident->load('unit');
        $currentUnit = Unit::factory()->create();
        User::whereKey($resident->id)->update(['unit_id' => $currentUnit->id]);
        $area = CommonArea::factory()->create();
        $reservation = app(ReservationService::class)->create($resident, $this->data($area) + [
            'user_id' => 99999, 'resident_id' => 99999, 'unit_id' => $resident->unit_id,
            'status' => 'confirmed', 'rejection_reason' => 'Injected',
        ]);
        $this->assertSame($resident->id, $reservation->user_id);
        $this->assertSame($currentUnit->id, $reservation->unit_id);
        $this->assertSame(ReservationStatus::Pending, $reservation->status);
        $this->assertNull($reservation->rejection_reason);
    }

    public function test_service_rejects_invalid_dates_and_missing_area_without_inserting(): void
    {
        $resident = User::factory()->morador()->create();
        $area = CommonArea::factory()->create();
        foreach ([['starts_at' => null], ['ends_at' => 'tomorrow'], ['starts_at' => '2026-02-30 14:00'], ['common_area_id' => 99999]] as $invalid) {
            try {
                app(ReservationService::class)->create($resident, $this->data($area, $invalid));
                $this->fail('Expected validation failure.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_conflict_read_and_insert_follow_area_read_inside_transaction(): void
    {
        $resident = User::factory()->morador()->create();
        $area = CommonArea::factory()->create();
        $baseline = DB::transactionLevel();
        $operations = [];
        DB::listen(function (QueryExecuted $query) use (&$operations): void {
            if (str_contains($query->sql, 'common_areas') || str_contains($query->sql, 'reservations')) {
                $operations[] = ['sql' => $query->sql, 'level' => $query->connection->transactionLevel()];
            }
        });
        app(ReservationService::class)->create($resident, $this->data($area));
        $this->assertCount(3, $operations);
        $this->assertStringContainsString('common_areas', $operations[0]['sql']);
        $this->assertStringContainsString('exists', $operations[1]['sql']);
        $this->assertStringContainsString('insert', $operations[2]['sql']);
        foreach ($operations as $operation) {
            $this->assertGreaterThan($baseline, $operation['level']);
        }
        $this->assertSame($baseline, DB::transactionLevel());
    }

    /** @return array{common_area_id: int, starts_at: string, ends_at: string} */
    private function data(CommonArea $area, array $overrides = []): array
    {
        return array_replace(['common_area_id' => $area->id, 'starts_at' => '2026-09-26 14:00', 'ends_at' => '2026-09-26 16:00'], $overrides);
    }

    private function availabilityUrl(CommonArea $area): string
    {
        return route('morador.common-areas.availability', ['commonArea' => $area->id, 'date' => '2026-09-26']);
    }

    private function occupy(CommonArea $area, ReservationStatus $status, string $start = '14:00', string $end = '16:00'): void
    {
        Reservation::factory()->create(['common_area_id' => $area->id, 'status' => $status,
            'starts_at' => '2026-09-26 '.$start, 'ends_at' => '2026-09-26 '.$end]);
    }
}
