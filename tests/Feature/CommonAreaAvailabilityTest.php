<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CommonAreaAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_areas_are_listed_and_empty_state_is_supported(): void
    {
        $this->actingAs(User::factory()->morador()->create());
        $this->get(route('morador.common-areas.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('morador/common-areas')->has('areas', 0));
        $active = CommonArea::factory()->create();
        CommonArea::factory()->create(['status' => 'inactive']);
        CommonArea::factory()->create(['status' => 'maintenance']);
        $this->get(route('morador.common-areas.index'))->assertInertia(fn (Assert $page) => $page
            ->has('areas', 1)->where('areas.0', ['id' => $active->id, 'name' => $active->name]));
    }

    public function test_area_contract_and_privacy_are_explicit_and_consultation_does_not_write(): void
    {
        $this->actingAs(User::factory()->morador()->create());
        foreach ([true, false] as $approval) {
            $area = CommonArea::factory()->create(['requires_approval' => $approval, 'maintenance_reason' => 'Privado']);
            $reservation = $this->occupy($area, '10:00', '12:00');
            $original = $reservation->refresh()->getRawOriginal();
            $this->getJson($this->url($area))->assertOk()->assertExactJson([
                'area' => $area->refresh()->only(['id', 'name', 'description', 'available_from', 'available_until', 'max_reservation_minutes', 'rules', 'requires_approval']),
                'date' => '2026-09-25',
                'occupied_periods' => [['start' => '10:00:00', 'end' => '12:00:00']],
                'free_periods' => [['start' => '08:00:00', 'end' => '10:00:00'], ['start' => '12:00:00', 'end' => '22:00:00']],
            ])->assertHeader('Cache-Control', 'no-store, private');
            $this->assertSame($original, $reservation->refresh()->getRawOriginal());
        }
        $this->assertDatabaseCount('reservations', 2);
    }

    #[DataProvider('statuses')]
    public function test_calendar_and_creation_agree_on_every_status(ReservationStatus $status, bool $blocks): void
    {
        $this->actingAs($resident = User::factory()->morador()->create());
        $area = CommonArea::factory()->create();
        $reservation = $this->occupy($area, '14:00', '16:00', $status);
        $response = $this->getJson($this->url($area))->assertOk();
        $response->assertJsonCount($blocks ? 1 : 0, 'occupied_periods');
        $data = ['common_area_id' => $area->id, 'user_id' => $resident->id, 'unit_id' => $reservation->unit_id,
            'starts_at' => '2026-09-25 14:00', 'ends_at' => '2026-09-25 16:00'];
        if ($blocks) {
            $this->expectException(ValidationException::class);
        }
        $created = app(ReservationService::class)->create($data);
        $this->assertModelExists($created);
    }

    public static function statuses(): array
    {
        return [
            [ReservationStatus::Pending, true], [ReservationStatus::Approved, true],
            [ReservationStatus::Rejected, false], [ReservationStatus::Cancelled, false], [ReservationStatus::Completed, false],
        ];
    }

    #[DataProvider('overlaps')]
    public function test_calendar_and_creation_agree_on_overlap_boundaries(string $existingEnd, string $start, string $end, bool $conflict): void
    {
        $this->actingAs($resident = User::factory()->morador()->create());
        $area = CommonArea::factory()->create();
        $reservation = $this->occupy($area, '14:00', $existingEnd);
        $response = $this->getJson($this->url($area))->assertOk()
            ->assertJsonPath('occupied_periods', [['start' => '14:00:00', 'end' => $existingEnd.':00']]);
        $fitsFreePeriod = collect($response->json('free_periods'))->contains(fn (array $period): bool => $start.':00' >= $period['start'] && $end.':00' <= $period['end']);
        $this->assertSame(! $conflict, $fitsFreePeriod);
        if ($conflict) {
            $this->expectException(ValidationException::class);
        }
        $created = app(ReservationService::class)->create([
            'common_area_id' => $area->id, 'user_id' => $resident->id, 'unit_id' => $reservation->unit_id,
            'starts_at' => '2026-09-25 '.$start, 'ends_at' => '2026-09-25 '.$end,
        ]);
        $this->assertModelExists($created);
    }

    public static function overlaps(): array
    {
        return [
            'partial before' => ['16:00', '13:00', '15:00', true],
            'partial after' => ['16:00', '15:00', '17:00', true],
            'inside' => ['18:00', '15:00', '16:00', true],
            'contains' => ['16:00', '13:00', '17:00', true],
            'equal' => ['16:00', '14:00', '16:00', true],
            'consecutive after' => ['16:00', '16:00', '18:00', false],
            'consecutive before' => ['16:00', '12:00', '14:00', false],
        ];
    }

    public function test_area_and_date_changes_only_return_matching_occupancy(): void
    {
        $this->actingAs(User::factory()->morador()->create());
        $area = CommonArea::factory()->create();
        $other = CommonArea::factory()->create();
        $this->occupy($area, '10:00', '12:00');
        $this->occupy($other, '15:30', '17:00');
        $next = $this->occupy($area, '18:00', '19:00');
        $next->update(['starts_at' => '2026-09-26 18:00', 'ends_at' => '2026-09-26 19:00']);
        $this->getJson($this->url($area))->assertJsonPath('occupied_periods', [['start' => '10:00:00', 'end' => '12:00:00']]);
        $this->getJson($this->url($other))->assertJsonPath('occupied_periods', [['start' => '15:30:00', 'end' => '17:00:00']]);
        $this->getJson($this->url($area, '2026-09-26'))->assertJsonPath('occupied_periods', [['start' => '18:00:00', 'end' => '19:00:00']]);
        $this->getJson($this->url($area, '2020-01-01'))->assertOk()->assertJsonCount(0, 'occupied_periods');
    }

    public function test_empty_full_and_overlapping_days_respect_operating_hours(): void
    {
        $this->actingAs(User::factory()->morador()->create());
        $area = CommonArea::factory()->create();
        $this->getJson($this->url($area))->assertJsonPath('free_periods', [['start' => '08:00:00', 'end' => '22:00:00']]);
        $this->occupy($area, '06:00', '12:00');
        $this->occupy($area, '10:00', '11:00');
        $this->occupy($area, '12:00', '23:00');
        $this->getJson($this->url($area))->assertJsonPath('free_periods', [])
            ->assertJsonPath('occupied_periods.0', ['start' => '08:00:00', 'end' => '12:00:00'])
            ->assertJsonPath('occupied_periods.2', ['start' => '12:00:00', 'end' => '22:00:00']);
    }

    public function test_null_schedule_retains_existing_unrestricted_same_day_behavior(): void
    {
        $this->actingAs($resident = User::factory()->morador()->create());
        $area = CommonArea::factory()->create(['available_from' => null, 'available_until' => null]);
        $this->getJson($this->url($area))->assertJsonPath('free_periods', [['start' => null, 'end' => null]]);
        $reservation = $this->occupy($area, '10:00', '12:00');
        $this->getJson($this->url($area))->assertJsonPath('free_periods', [
            ['start' => null, 'end' => '10:00:00'], ['start' => '12:00:00', 'end' => null],
        ]);
        $created = app(ReservationService::class)->create([
            'common_area_id' => $area->id, 'user_id' => $resident->id, 'unit_id' => $reservation->unit_id,
            'starts_at' => '2026-09-25 23:00', 'ends_at' => '2026-09-25 23:59:59',
        ]);
        $this->assertModelExists($created);
    }

    #[DataProvider('invalidDates')]
    public function test_invalid_dates_are_rejected(mixed $date): void
    {
        $this->actingAs(User::factory()->morador()->create());
        $this->getJson($this->url(CommonArea::factory()->create(), $date))
            ->assertUnprocessable()->assertJsonValidationErrors('date');
    }

    public static function invalidDates(): array
    {
        return [[null], [''], ['25/09/2026'], ['2026-02-30'], ['2026-9-25'], ['tomorrow'], [['2026-09-25']]];
    }

    public function test_missing_and_non_active_areas_are_not_available(): void
    {
        $this->actingAs(User::factory()->morador()->create());
        $this->getJson(route('morador.common-areas.availability', ['commonArea' => 99999, 'date' => '2026-09-25']))->assertNotFound();
        foreach (['inactive', 'maintenance'] as $status) {
            $area = CommonArea::factory()->create(['status' => $status]);
            $this->getJson($this->url($area))->assertNotFound();
        }
    }

    public function test_seconds_and_small_gaps_are_preserved_without_slots(): void
    {
        $this->actingAs(User::factory()->morador()->create());
        $area = CommonArea::factory()->create();
        $this->occupy($area, '08:00:00', '10:00:15');
        $this->occupy($area, '10:00:45', '22:00:00');
        $this->getJson($this->url($area))->assertOk()->assertJsonPath('free_periods', [
            ['start' => '10:00:15', 'end' => '10:00:45'],
        ]);
    }

    public function test_legacy_outside_hours_and_day_boundaries_do_not_block_free_time(): void
    {
        $this->actingAs(User::factory()->morador()->create());
        $area = CommonArea::factory()->create();
        $this->occupy($area, '06:00', '08:00');
        $this->occupy($area, '22:00', '23:00');
        $previous = $this->occupy($area, '00:00', '01:00');
        $previous->update(['starts_at' => '2026-09-24 22:00', 'ends_at' => '2026-09-25 00:00']);
        $this->getJson($this->url($area))->assertOk()->assertJsonCount(0, 'occupied_periods')
            ->assertJsonPath('free_periods', [['start' => '08:00:00', 'end' => '22:00:00']]);
    }

    #[DataProvider('invalidSchedules')]
    public function test_shared_service_still_enforces_hours_duration_and_same_day(string $start, string $end, string $field): void
    {
        $area = CommonArea::factory()->create();
        $resident = User::factory()->morador()->create();
        try {
            app(ReservationService::class)->create([
                'common_area_id' => $area->id, 'user_id' => $resident->id, 'unit_id' => $resident->unit_id,
                'starts_at' => $start, 'ends_at' => $end,
            ]);
            $this->fail('Expected a schedule validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertDatabaseCount('reservations', 0);
    }

    public static function invalidSchedules(): array
    {
        return [
            ['2026-09-25 07:00', '2026-09-25 09:00', 'starts_at'],
            ['2026-09-25 21:00', '2026-09-25 23:00', 'ends_at'],
            ['2026-09-25 10:00', '2026-09-25 15:00', 'ends_at'],
            ['2026-09-25 21:00', '2026-09-26 01:00', 'ends_at'],
        ];
    }

    #[DataProvider('blockedUsers')]
    public function test_both_routes_are_protected(string $actor, string $expected): void
    {
        $area = CommonArea::factory()->create();
        foreach ([route('morador.common-areas.index'), $this->url($area)] as $url) {
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
            $response = $this->get($url);
            if ($expected === 'forbidden') {
                $response->assertForbidden();
            } else {
                $response->assertRedirect(route($expected));
            }
        }
    }

    public static function blockedUsers(): array
    {
        return [['guest', 'login'], ['admin', 'forbidden'], ['porteiro', 'forbidden'], ['inactive', 'login'], ['unverified', 'verification.notice']];
    }

    private function url(CommonArea $area, mixed $date = '2026-09-25'): string
    {
        return route('morador.common-areas.availability', ['commonArea' => $area->id, 'date' => $date]);
    }

    private function occupy(CommonArea $area, string $start, string $end, ReservationStatus $status = ReservationStatus::Approved): Reservation
    {
        return Reservation::factory()->create([
            'common_area_id' => $area->id, 'starts_at' => '2026-09-25 '.$start,
            'ends_at' => '2026-09-25 '.$end, 'status' => $status,
        ]);
    }
}
