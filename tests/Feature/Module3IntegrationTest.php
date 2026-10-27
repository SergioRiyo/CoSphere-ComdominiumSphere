<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\CommonArea;
use App\Models\CommonAreaBlock;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Module3IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-30 10:00:00'));
        config(['inertia.ssr.enabled' => false]);
    }

    #[DataProvider('reservationModes')]
    public function test_reservation_journey_connects_calendar_lifecycle_history_and_inbox(bool $manual): void
    {
        $resident = User::factory()->morador()->create();
        $admin = User::factory()->admin()->create();
        $area = CommonArea::factory()->create(['requires_approval' => $manual]);
        $this->actingAs($resident)->get(route('morador.common-areas.index'))
            ->assertInertia(fn (Assert $page) => $page->component('morador/common-areas')->where('areas.0.id', $area->id));
        $this->calendar($area)->assertJsonPath('occupied_periods', []);
        $this->postJson(route('morador.reservations.store'), $this->period($area))->assertCreated();
        $reservation = Reservation::sole();
        $initial = $manual ? ReservationStatus::Pending : ReservationStatus::Approved;
        $this->assertSame($initial, $reservation->status);
        $this->assertSame($resident->id, $reservation->user_id);
        $this->assertSame($resident->unit_id, $reservation->unit_id);
        $this->assertSame('2026-10-01 14:00:00', $reservation->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-01 16:00:00', $reservation->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame($initial, $reservation->statusHistory()->sole()->to_status);
        $this->calendar($area)->assertJsonPath('occupied_periods.0', ['start' => '14:00:00', 'end' => '16:00:00']);

        if ($manual) {
            $this->travel(1)->minutes();
            $this->actingAs($admin)->patchJson(route('admin.reservations.approve', $reservation))->assertOk();
            $this->assertSame(ReservationStatus::Approved, $reservation->refresh()->status);
            $this->actingAs($resident);
            $this->calendar($area)->assertJsonPath('occupied_periods.0', ['start' => '14:00:00', 'end' => '16:00:00']);
        }

        $this->travel(1)->minutes();
        $this->patchJson(route('morador.reservations.cancel', $reservation))->assertOk();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->refresh()->status);
        $this->assertModelExists($reservation);
        $this->calendar($area)->assertJsonPath('occupied_periods', [])
            ->assertJsonPath('free_periods', [['start' => '08:00:00', 'end' => '22:00:00']]);
        $history = $reservation->statusHistory()->get();
        $this->assertSame($manual ? ['pending', 'confirmed', 'cancelled'] : ['confirmed', 'cancelled'], $history->pluck('to_status')->map(fn ($status) => $status->value)->all());
        $this->assertSame($manual ? [$resident->id, $admin->id, $resident->id] : [$resident->id, $resident->id], $history->pluck('changed_by_user_id')->all());
        $this->assertSame($manual ? ['morador', 'admin', 'morador'] : ['morador', 'morador'], $history->pluck('actor_role')->map(fn ($role) => $role->value)->all());
        $this->assertTrue($history->first()->created_at->lessThan($history->last()->created_at));
        $this->get(route('morador.reservations.show', $reservation))->assertInertia(fn (Assert $page) => $page
            ->component('morador/reservation-details')->where('reservation.status', 'cancelled')->has('reservation.history', $manual ? 3 : 2));
        $this->get(route('morador.notifications.index'))->assertInertia(fn (Assert $page) => $page
            ->component('morador/notifications/index')->has('notifications.data', $manual ? 2 : 1));
        foreach (Notification::all() as $notification) {
            $this->assertSame($resident->id, $notification->recipient_id);
            $this->assertSame(NotificationType::Reservation, $notification->type);
            foreach ([$area->name, '01/10/2026', '14:00:00', '16:00:00'] as $context) {
                $this->assertStringContainsString($context, $notification->message);
            }
        }
        $notification = Notification::firstOrFail();
        $this->patch(route('morador.notifications.read', $notification))->assertRedirect();
        $this->assertTrue($notification->refresh()->is_read);
        $this->assertDatabaseCount('notifications', $manual ? 2 : 1);
    }

    public static function reservationModes(): array
    {
        return [[false], [true]];
    }

    public function test_rejection_and_pending_cancellation_release_the_period_without_erasing_records(): void
    {
        $resident = User::factory()->morador()->create();
        $admin = User::factory()->admin()->create();
        $area = CommonArea::factory()->create();
        $this->actingAs($resident)->postJson(route('morador.reservations.store'), $this->period($area))->assertCreated();
        $reservation = Reservation::sole();
        $this->actingAs($admin)->patchJson(route('admin.reservations.reject', $reservation), ['rejection_reason' => 'Manutenção programada'])->assertOk();
        $this->assertSame(ReservationStatus::Rejected, $reservation->refresh()->status);
        $this->assertSame('Manutenção programada', $reservation->rejection_reason);
        $this->assertSame($admin->id, $reservation->statusHistory()->get()->last()->changed_by_user_id);
        $this->assertSame('Manutenção programada', $reservation->statusHistory()->get()->last()->reason);
        $this->assertSame($resident->id, Notification::orderByDesc('id')->firstOrFail()->recipient_id);
        $this->assertStringContainsString('Manutenção programada', Notification::orderByDesc('id')->firstOrFail()->message);
        $this->actingAs($resident);
        $this->calendar($area)->assertJsonPath('occupied_periods', []);
        $this->postJson(route('morador.reservations.store'), $this->period($area))->assertCreated();
        $pending = Reservation::orderByDesc('id')->firstOrFail();
        $this->patchJson(route('morador.reservations.cancel', $pending))->assertOk();
        $this->calendar($area)->assertJsonPath('occupied_periods', []);
        $this->assertDatabaseCount('reservations', 2);
        $this->assertDatabaseCount('reservation_status_histories', 4);
        $this->assertDatabaseCount('notifications', 3);
        $this->get(route('morador.reservations.index'))->assertInertia(fn (Assert $page) => $page->has('reservations.data', 2));
    }

    public function test_created_reservation_rejects_overlap_but_accepts_adjacent_http_request(): void
    {
        $area = CommonArea::factory()->create(['requires_approval' => false]);
        $this->actingAs(User::factory()->morador()->create())->postJson(route('morador.reservations.store'), $this->period($area))->assertCreated();
        $this->actingAs(User::factory()->morador()->create())->postJson(route('morador.reservations.store'), $this->period($area, '15:00', '17:00'))->assertUnprocessable();
        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseCount('reservation_status_histories', 1);
        $this->assertDatabaseCount('notifications', 1);
        $this->postJson(route('morador.reservations.store'), $this->period($area, '16:00', '18:00'))->assertCreated();
        $this->assertDatabaseCount('reservations', 2);
        $this->calendar($area)->assertJsonCount(2, 'occupied_periods');
    }

    public function test_block_journey_preserves_pending_reservation_and_rechecks_defensive_approval(): void
    {
        $admin = User::factory()->admin()->create();
        $resident = User::factory()->morador()->create();
        $area = CommonArea::factory()->create();
        $blockData = $this->period($area) + ['reason' => 'Manutenção'];
        $this->actingAs($resident)->postJson(route('morador.reservations.store'), $this->period($area))->assertCreated();
        $reservation = Reservation::sole();
        $this->actingAs($admin)->postJson(route('admin.common-area-blocks.store'), $blockData)->assertUnprocessable();
        $this->assertDatabaseCount('common_area_blocks', 0);
        $this->assertSame(ReservationStatus::Pending, $reservation->refresh()->status);
        $this->patchJson(route('admin.reservations.cancel', $reservation))->assertOk();
        $this->postJson(route('admin.common-area-blocks.store'), $blockData)->assertCreated();
        $block = CommonAreaBlock::sole();
        $this->assertSame($admin->id, $block->admin_id);
        $this->actingAs($resident);
        $this->calendar($area)->assertJsonPath('blocked_periods.0', ['start' => '14:00:00', 'end' => '16:00:00']);
        $this->postJson(route('morador.reservations.store'), $this->period($area))->assertUnprocessable();
        $legacy = Reservation::factory()->create($this->period($area) + ['user_id' => $resident->id, 'unit_id' => $resident->unit_id, 'status' => ReservationStatus::Pending]);
        $this->actingAs($admin)->patchJson(route('admin.reservations.approve', $legacy))->assertUnprocessable();
        $this->assertSame(ReservationStatus::Pending, $legacy->refresh()->status);
        $this->assertSame(0, $legacy->statusHistory()->count());
        $this->assertDatabaseCount('notifications', 2);
        $this->patchJson(route('admin.reservations.reject', $legacy), ['rejection_reason' => 'Bloqueio vigente'])->assertOk();
        $this->deleteJson(route('admin.common-area-blocks.destroy', $block))->assertOk();
        $this->actingAs($resident);
        $this->calendar($area)->assertJsonPath('blocked_periods', [])->assertJsonPath('occupied_periods', []);
        $this->postJson(route('morador.reservations.store'), $this->period($area))->assertCreated();
    }

    #[DataProvider('orderJourneys')]
    public function test_order_journey_preserves_record_unit_operator_and_private_notification(bool $expected, bool $residentPickup): void
    {
        $resident = User::factory()->morador()->create();
        $housemate = User::factory()->morador()->create(['unit_id' => $resident->unit_id]);
        $outsider = User::factory()->morador()->create();
        $doorman = User::factory()->porteiro()->create();
        $data = ['description' => 'Livros da demonstração', 'tracking_code' => 'M3-14-DEMO'];
        if ($expected) {
            $this->actingAs($resident)->post(route('morador.orders.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
            $order = Order::sole();
            $this->assertSame(OrderStatus::WaitingDelivery, $order->status);
            $this->assertNull($order->received_at);
            $this->assertDatabaseCount('notifications', 0);
            $this->actingAs($doorman)->get(route('portaria.orders.index'))->assertInertia(fn (Assert $page) => $page->where('orders.data.0.id', $order->id));
            $this->patch(route('portaria.orders.receive', $order))->assertSessionHasNoErrors()->assertRedirect();
        } else {
            $this->actingAs($doorman)->post(route('portaria.orders.store'), $data + ['unit_id' => $resident->unit_id, 'resident_id' => $resident->id])->assertSessionHasNoErrors()->assertRedirect();
            $order = Order::sole();
        }
        $order->refresh();
        $this->assertSame(OrderStatus::ReceivedAtGate, $order->status);
        $this->assertSame($resident->id, $order->resident_id);
        $this->assertSame($resident->unit_id, $order->unit_id);
        $this->assertSame($doorman->id, $order->received_by_id);
        $this->assertTrue($order->received_at->equalTo(now()));
        $this->assertDatabaseCount('orders', 1);
        $notification = Notification::sole();
        $this->assertSame(NotificationType::Package, $notification->type);
        $this->assertSame($resident->id, $notification->recipient_id);
        $this->assertStringContainsString('M3-14-DEMO', $notification->message);
        $this->actingAs($housemate)->get(route('morador.orders.show', $order))->assertInertia(fn (Assert $page) => $page->where('order.id', $order->id)->where('order.status', 'received_at_gate'));
        $this->get(route('morador.notifications.index'))->assertInertia(fn (Assert $page) => $page->has('notifications.data', 0));
        $this->actingAs($outsider)->get(route('morador.orders.show', $order))->assertNotFound();
        $this->patch(route('morador.orders.pickup', $order))->assertNotFound();
        $this->actingAs($resident)->get(route('morador.notifications.index'))->assertInertia(fn (Assert $page) => $page->where('notifications.data.0.id', $notification->id));

        $this->travel(5)->minutes();
        $operator = $residentPickup ? $housemate : $doorman;
        $prefix = $residentPickup ? 'morador' : 'portaria';
        $this->actingAs($operator)->patch(route($prefix.'.orders.pickup', $order))->assertSessionHasNoErrors()->assertRedirect();
        $order->refresh();
        $this->assertSame(OrderStatus::PickedUp, $order->status);
        $this->assertSame($operator->id, $order->picked_up_by_id);
        $this->assertTrue($order->picked_up_at->equalTo(now()));
        $firstState = $order->getRawOriginal();
        $this->travel(1)->minutes();
        $this->actingAs($residentPickup ? $doorman : $resident)->patch(route(($residentPickup ? 'portaria' : 'morador').'.orders.pickup', $order))->assertSessionHasErrors('order');
        $this->assertSame($firstState, $order->refresh()->getRawOriginal());
        $this->assertDatabaseCount('notifications', 1);
        $this->actingAs($resident)->get(route('morador.orders.index'))->assertInertia(fn (Assert $page) => $page->where('orders.data.0.id', $order->id)->where('orders.data.0.status', 'picked_up'));
        $this->get(route('morador.orders.show', $order))->assertInertia(fn (Assert $page) => $page->component('orders/show')
            ->where('order.received_by', $doorman->name)->where('order.pickup_confirmed_by', $operator->name)->where('order.can_pickup', false));
        $this->actingAs($doorman)->get(route('portaria.order-history.index'))->assertInertia(fn (Assert $page) => $page->where('orders.data.0.id', $order->id));
    }

    public static function orderJourneys(): array
    {
        return [[true, false], [true, true], [false, false], [false, true]];
    }

    private function period(CommonArea $area, string $start = '14:00', string $end = '16:00'): array
    {
        return ['common_area_id' => $area->id, 'starts_at' => '2026-10-01 '.$start, 'ends_at' => '2026-10-01 '.$end];
    }

    private function calendar(CommonArea $area): TestResponse
    {
        return $this->getJson(route('morador.common-areas.availability', ['commonArea' => $area->id, 'date' => '2026-10-01']));
    }
}
