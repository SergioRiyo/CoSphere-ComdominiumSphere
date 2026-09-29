<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\CommonArea;
use App\Models\CommonAreaBlock;
use App\Models\Reservation;
use App\Models\User;
use App\Services\CommonAreaBlockService;
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

class CommonAreaBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-29 10:00:00'));
    }

    public function test_admin_creates_block_with_backend_author_and_only_validated_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $area = CommonArea::factory()->create();
        $this->actingAs($admin)->postJson(route('admin.common-area-blocks.store'), $this->data($area) + [
            'admin_id' => 99999, 'created_by' => 99999, 'id' => 99999, 'created_at' => '2000-01-01',
            'updated_at' => '2000-01-01', 'status' => 'maintenance',
        ])->assertCreated();
        $block = CommonAreaBlock::sole();
        $this->assertTrue($block->commonArea->is($area));
        $this->assertTrue($block->admin->is($admin));
        $this->assertSame('Manutenção elétrica', $block->reason);
        $this->assertSame('2026-09-30 14:00:00', $block->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-30 16:00:00', $block->ends_at->format('Y-m-d H:i:s'));
        $this->assertTrue($block->created_at->equalTo(now()));
        $this->assertTrue($block->updated_at->equalTo(now()));
        $this->assertSame('active', $area->refresh()->status);
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    #[DataProvider('invalidData')]
    public function test_invalid_input_is_rejected_by_http_and_service(array $invalid, string $field): void
    {
        $admin = User::factory()->admin()->create();
        $area = CommonArea::factory()->create();
        $data = $this->data($area, $invalid);
        $this->actingAs($admin)->postJson(route('admin.common-area-blocks.store'), $data)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        try {
            app(CommonAreaBlockService::class)->create($admin, $data);
            $this->fail('Expected validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertDatabaseCount('common_area_blocks', 0);
    }

    public static function invalidData(): array
    {
        return [
            [['common_area_id' => 99999], 'common_area_id'],
            [['common_area_id' => null], 'common_area_id'],
            [['common_area_id' => 'invalid'], 'common_area_id'],
            [['starts_at' => null], 'starts_at'], [['ends_at' => null], 'ends_at'],
            [['reason' => null], 'reason'], [['reason' => '   '], 'reason'],
            [['reason' => ['invalid']], 'reason'], [['reason' => str_repeat('é', 256)], 'reason'],
            [['starts_at' => 'tomorrow'], 'starts_at'], [['ends_at' => '2026-02-30 16:00'], 'ends_at'],
            [['starts_at' => '2026-09-30T14:00:00Z'], 'starts_at'],
            [['ends_at' => '2026-09-30 14:00'], 'ends_at'],
            [['ends_at' => '2026-09-30 13:00'], 'ends_at'],
            [['ends_at' => '2026-10-01 16:00'], 'ends_at'],
        ];
    }

    public function test_block_is_not_limited_by_reservation_duration_or_opening_hours(): void
    {
        $area = CommonArea::factory()->create(['max_reservation_minutes' => 30]);
        $block = app(CommonAreaBlockService::class)->create(User::factory()->admin()->create(), $this->data($area, [
            'starts_at' => '2026-09-30 00:00:00', 'ends_at' => '2026-09-30 23:59:59', 'reason' => str_repeat('é', 255),
        ]));
        $this->assertSame(255, mb_strlen($block->reason));
    }

    #[DataProvider('reservationStatuses')]
    public function test_reservation_status_policy_preserves_existing_reservation(ReservationStatus $status, bool $conflicts): void
    {
        $area = CommonArea::factory()->create();
        $reservation = Reservation::factory()->create($this->period($area) + ['status' => $status]);
        $original = $reservation->refresh()->getRawOriginal();
        $response = $this->actingAs(User::factory()->admin()->create())->postJson(route('admin.common-area-blocks.store'), $this->data($area));
        $conflicts ? $response->assertUnprocessable() : $response->assertCreated();
        $this->assertDatabaseCount('common_area_blocks', $conflicts ? 0 : 1);
        $this->assertSame($original, $reservation->refresh()->getRawOriginal());
    }

    public static function reservationStatuses(): array
    {
        return [[ReservationStatus::Pending, true], [ReservationStatus::Approved, true], [ReservationStatus::Cancelled, false], [ReservationStatus::Rejected, false], [ReservationStatus::Completed, false]];
    }

    #[DataProvider('overlaps')]
    public function test_all_writes_use_strict_overlap_boundaries(string $start, string $end, bool $conflicts): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $area = CommonArea::factory()->create();
        $existing = CommonAreaBlock::factory()->create($this->period($area));
        $data = $this->data($area, ['starts_at' => '2026-09-30 '.$start, 'ends_at' => '2026-09-30 '.$end]);
        $response = $this->actingAs($admin)->postJson(route('admin.common-area-blocks.store'), $data);
        $conflicts ? $response->assertUnprocessable() : $response->assertCreated();
        CommonAreaBlock::whereKeyNot($existing->id)->delete();
        $response = $this->actingAs($resident)->postJson(route('morador.reservations.store'), $data);
        $conflicts ? $response->assertUnprocessable()->assertJsonValidationErrors('starts_at') : $response->assertCreated();
        $this->assertDatabaseCount('reservations', $conflicts ? 0 : 1);
        Reservation::query()->delete();
        $pending = Reservation::factory()->create(array_intersect_key($data, $this->period($area)) + ['status' => ReservationStatus::Pending]);
        $response = $this->actingAs($admin)->patchJson(route('admin.reservations.approve', $pending));
        $conflicts ? $response->assertUnprocessable() : $response->assertOk();
        $this->assertSame($conflicts ? ReservationStatus::Pending : ReservationStatus::Approved, $pending->refresh()->status);
        $existing->delete();
        $response = $this->postJson(route('admin.common-area-blocks.store'), $this->data($area));
        $conflicts ? $response->assertUnprocessable() : $response->assertCreated();
    }

    public static function overlaps(): array
    {
        return [
            ['13:00', '15:00', true], ['15:00', '17:00', true], ['14:30', '15:30', true],
            ['13:00', '17:00', true], ['14:00', '16:00', true],
            ['12:00', '14:00', false], ['16:00', '18:00', false], ['16:00:01', '18:00', false],
        ];
    }

    public function test_calendar_combines_orders_and_clips_both_sources_without_exposing_admin_data(): void
    {
        $area = CommonArea::factory()->create();
        CommonAreaBlock::factory()->create($this->period($area, '06:00', '09:00'));
        CommonAreaBlock::factory()->create($this->period($area, '12:00', '15:00'));
        CommonAreaBlock::factory()->create($this->period($area, '21:00', '23:00'));
        Reservation::factory()->create($this->period($area, '10:00', '13:00'));
        $this->actingAs(User::factory()->morador()->create())->getJson($this->calendar($area))->assertOk()
            ->assertJsonPath('free_periods', [['start' => '09:00:00', 'end' => '10:00:00'], ['start' => '15:00:00', 'end' => '21:00:00']])
            ->assertJsonPath('blocked_periods', [['start' => '08:00:00', 'end' => '09:00:00'], ['start' => '12:00:00', 'end' => '15:00:00'], ['start' => '21:00:00', 'end' => '22:00:00']])
            ->assertJsonPath('occupied_periods', [['start' => '10:00:00', 'end' => '13:00:00']])
            ->assertDontSee('admin_id')->assertDontSee('reason')->assertDontSee('Manutenção elétrica')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_blocks_only_affect_matching_area_and_date_and_keep_null_boundaries(): void
    {
        $area = CommonArea::factory()->create(['available_from' => null, 'available_until' => null]);
        CommonAreaBlock::factory()->create($this->period($area, '00:00', '10:00'));
        CommonAreaBlock::factory()->create($this->period(CommonArea::factory()->create()));
        CommonAreaBlock::factory()->create($this->period($area))->update(['starts_at' => '2026-10-01 14:00', 'ends_at' => '2026-10-01 16:00']);
        $this->actingAs(User::factory()->morador()->create())->getJson($this->calendar($area))
            ->assertJsonPath('blocked_periods', [['start' => null, 'end' => '10:00:00']])
            ->assertJsonPath('free_periods', [['start' => '10:00:00', 'end' => null]]);
    }

    public function test_removal_recalculates_availability_and_ignores_operational_payload(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $area = CommonArea::factory()->create();
        $block = CommonAreaBlock::factory()->create($this->period($area));
        $other = CommonAreaBlock::factory()->create();
        $this->actingAs($resident)->getJson($this->calendar($area))->assertJsonCount(1, 'blocked_periods');
        $this->actingAs($admin)->deleteJson(route('admin.common-area-blocks.destroy', $block), ['id' => $other->id, 'common_area_id' => $other->common_area_id])->assertOk();
        $this->assertModelMissing($block);
        $this->assertModelExists($other);
        $this->actingAs($resident)->getJson($this->calendar($area))->assertJsonPath('blocked_periods', [])->assertJsonPath('free_periods', [['start' => '08:00:00', 'end' => '22:00:00']]);
        $this->assertDatabaseCount('notifications', 0);
    }

    #[DataProvider('remainingSources')]
    public function test_removal_does_not_erase_other_sources(string $source): void
    {
        $area = CommonArea::factory()->create();
        $block = CommonAreaBlock::factory()->create($this->period($area));
        if ($source === 'block') {
            CommonAreaBlock::factory()->create($this->period($area));
        } elseif (in_array($source, ['pending', 'confirmed'])) {
            $reservation = Reservation::factory()->create($this->period($area) + ['status' => $source]);
            $original = $reservation->refresh()->getRawOriginal();
        } else {
            $area->update(['status' => $source]);
        }
        $this->actingAs(User::factory()->admin()->create())->deleteJson(route('admin.common-area-blocks.destroy', $block))->assertOk();
        $response = $this->actingAs(User::factory()->morador()->create())->getJson($this->calendar($area));
        if (in_array($source, ['inactive', 'maintenance'])) {
            $response->assertNotFound();
            $this->assertSame($source, $area->refresh()->status);
        } else {
            $response->assertOk()->assertJsonPath('free_periods', [['start' => '08:00:00', 'end' => '14:00:00'], ['start' => '16:00:00', 'end' => '22:00:00']]);
        }
        if (isset($reservation)) {
            $this->assertSame($original, $reservation->refresh()->getRawOriginal());
        }
    }

    public static function remainingSources(): array
    {
        return [['block'], ['pending'], ['confirmed'], ['inactive'], ['maintenance']];
    }

    public function test_inactive_and_maintenance_areas_do_not_accept_blocks(): void
    {
        foreach (['inactive', 'maintenance'] as $status) {
            $area = CommonArea::factory()->create(['status' => $status]);
            $this->actingAs(User::factory()->admin()->create())->postJson(route('admin.common-area-blocks.store'), $this->data($area))->assertUnprocessable()->assertJsonValidationErrors('common_area_id');
        }
        $this->assertDatabaseCount('common_area_blocks', 0);
    }

    #[DataProvider('unauthorizedActors')]
    public function test_routes_are_protected(string $actor, int $status): void
    {
        $block = CommonAreaBlock::factory()->create();
        if ($actor !== 'guest') {
            $factory = User::factory()->admin();
            $user = match ($actor) {
                'morador' => $factory->morador()->create(),
                'porteiro' => $factory->porteiro()->create(),
                'inactive' => $factory->inactive()->create(),
                default => $factory->unverified()->create(),
            };
            $this->actingAs($user);
        }
        $this->getJson(route('admin.common-area-blocks.index'))->assertStatus($status);
        if (isset($user)) {
            $this->actingAs($user);
        }
        $this->postJson(route('admin.common-area-blocks.store'), $this->data($block->commonArea))->assertStatus($status);
        if (isset($user)) {
            $this->actingAs($user);
        }
        $this->deleteJson(route('admin.common-area-blocks.destroy', $block))->assertStatus($status);
        $this->assertModelExists($block);
        $this->assertDatabaseCount('common_area_blocks', 1);
    }

    public static function unauthorizedActors(): array
    {
        return [['guest', 401], ['morador', 403], ['porteiro', 403], ['inactive', 302], ['unverified', 403]];
    }

    public function test_service_revalidates_stale_admin_for_create_and_remove(): void
    {
        foreach ([['is_active' => false], ['email_verified_at' => null], ['role' => 'morador']] as $changes) {
            $admin = User::factory()->admin()->create();
            $block = CommonAreaBlock::factory()->create();
            User::whereKey($admin->id)->update($changes);
            foreach (['create', 'remove'] as $action) {
                try {
                    $service = app(CommonAreaBlockService::class);
                    $action === 'create' ? $service->create($admin, $this->data($block->commonArea)) : $service->remove($admin, $block);
                    $this->fail('Expected authorization failure.');
                } catch (AuthorizationException) {
                    $this->assertModelExists($block);
                }
            }
        }
        $this->assertDatabaseCount('common_area_blocks', 3);
    }

    public function test_admin_listing_is_paginated_with_only_required_details_and_active_choices(): void
    {
        $admin = User::factory()->admin()->create();
        $area = CommonArea::factory()->create();
        $inactive = CommonArea::factory()->create(['status' => 'inactive']);
        CommonAreaBlock::factory()->count(16)->create($this->period($area) + ['admin_id' => $admin->id]);
        CommonAreaBlock::factory()->create($this->period($inactive) + ['admin_id' => $admin->id]);
        $this->actingAs($admin)->get(route('admin.common-area-blocks.index'))->assertInertia(fn (Assert $page) => $page
            ->component('admin/common-area-blocks')->has('blocks.data', 15)->where('blocks.total', 17)
            ->where('blocks.data.0', ['id' => CommonAreaBlock::max('id'), 'area' => $inactive->name, 'date' => '2026-09-30', 'start' => '14:00:00', 'end' => '16:00:00', 'reason' => 'Manutenção elétrica', 'admin' => $admin->name])
            ->has('areas', 1)->where('areas.0.id', $area->id));
        $this->get(route('admin.common-area-blocks.index', ['page' => 2]))->assertInertia(fn (Assert $page) => $page->has('blocks.data', 2));
        $this->deleteJson(route('admin.common-area-blocks.destroy', 99999))->assertNotFound();
    }

    public function test_area_lock_precedes_conflict_queries_and_insert_inside_transaction(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $area = CommonArea::factory()->create();
        $baseline = DB::transactionLevel();
        $operations = [];
        DB::listen(function (QueryExecuted $query) use (&$operations): void {
            if (str_contains($query->sql, 'common_areas') || str_contains($query->sql, 'reservations') || str_contains($query->sql, 'common_area_blocks')) {
                $operations[] = ['sql' => $query->sql, 'level' => $query->connection->transactionLevel()];
            }
        });
        app(CommonAreaBlockService::class)->create($admin, $this->data($area));
        $this->assertCount(4, $operations);
        $this->assertStringContainsString('common_areas', $operations[0]['sql']);
        $this->assertStringContainsString('reservations', $operations[1]['sql']);
        $this->assertStringContainsString('exists', $operations[2]['sql']);
        $this->assertStringContainsString('insert', $operations[3]['sql']);
        foreach ($operations as $operation) {
            $this->assertGreaterThan($baseline, $operation['level']);
        }
        $operations = [];
        app(ReservationService::class)->create($resident, $this->period($area, '16:00', '18:00'));
        $this->assertCount(4, $operations);
        $this->assertStringContainsString('common_areas', $operations[0]['sql']);
        $this->assertStringContainsString('common_area_blocks', $operations[2]['sql']);
        $this->assertStringContainsString('insert', $operations[3]['sql']);
        foreach ($operations as $operation) {
            $this->assertGreaterThan($baseline, $operation['level']);
        }
        $this->assertSame($baseline, DB::transactionLevel());
    }

    private function period(CommonArea $area, string $start = '14:00', string $end = '16:00'): array
    {
        return ['common_area_id' => $area->id, 'starts_at' => '2026-09-30 '.$start, 'ends_at' => '2026-09-30 '.$end];
    }

    private function data(CommonArea $area, array $overrides = []): array
    {
        return array_replace($this->period($area) + ['reason' => '  Manutenção elétrica  '], $overrides);
    }

    private function calendar(CommonArea $area): string
    {
        return route('morador.common-areas.availability', ['commonArea' => $area->id, 'date' => '2026-09-30']);
    }
}
