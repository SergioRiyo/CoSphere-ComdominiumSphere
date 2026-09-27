<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    public static function statuses(): array
    {
        return array_map(fn (OrderStatus $status): array => [$status], OrderStatus::cases());
    }

    #[DataProvider('statuses')]
    public function test_period_uses_date_of_current_status_including_day_boundaries(OrderStatus $status): void
    {
        config(['app.timezone' => 'America/Sao_Paulo']);
        $resident = User::factory()->create();
        $column = Order::statusDateColumn($status);
        $ids = [];
        foreach (['2026-08-09 23:59:59', '2026-08-10 00:00:00', '2026-08-10 23:59:59', '2026-08-11 00:00:00'] as $date) {
            $order = Order::factory()->create([
                'resident_id' => $resident->id, 'unit_id' => $resident->unit_id,
                'status' => $status, 'created_at' => '2026-01-01 12:00:00',
                'received_at' => '2026-01-02 12:00:00', 'picked_up_at' => '2026-01-03 12:00:00',
                'updated_at' => '2026-01-04 12:00:00',
                $column => $date,
            ]);
            $ids[] = $order->id;
        }
        $this->actingAs($resident)->get(route('morador.orders.index', ['status' => $status->value, 'date_from' => '2026-08-10', 'date_to' => '2026-08-10']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 2)
                ->where('orders.data.0.id', $ids[2])->where('orders.data.1.id', $ids[1]));
        $this->get(route('morador.orders.index', ['date_from' => '2026-08-10']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 3));
        $this->get(route('morador.orders.index', ['date_to' => '2026-08-10']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 3));
    }

    public function test_all_statuses_and_correct_operator_names_are_visible_in_details(): void
    {
        $resident = User::factory()->create();
        $doorman = User::factory()->porteiro()->create();
        foreach (OrderStatus::cases() as $status) {
            $order = Order::factory()->create(['unit_id' => $resident->unit_id, 'resident_id' => $resident->id,
                'status' => $status, 'received_by_id' => $doorman->id, 'picked_up_by_id' => $doorman->id]);
            $this->actingAs($resident)->get(route('morador.orders.index', ['status' => $status->value]))
                ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $order->id));
            $this->get(route('morador.orders.show', $order))->assertInertia(fn (Assert $page) => $page
                ->component('orders/show')->where('order.status', $status->value)
                ->where('order.received_by', $doorman->name)->where('order.pickup_confirmed_by', $doorman->name)
                ->where('order.created_at', $order->created_at->toISOString())
                ->where('order.available_for_pickup', $status === OrderStatus::ReceivedAtGate)
                ->missing('order.resident.email')->missing('order.resident.cpf'));
        }
        $this->get(route('morador.orders.index'))->assertInertia(fn (Assert $page) => $page->has('orders.data', 4));
    }

    public function test_history_stays_in_original_unit_after_recipient_moves(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PickedUp]);
        $originalUnit = $order->unit_id;
        $remainingResident = User::factory()->create(['unit_id' => $originalUnit]);
        $newUnit = Unit::factory()->create();
        $recipient = $order->resident;
        $recipient->update(['unit_id' => $newUnit->id]);
        $this->actingAs($recipient)->get(route('morador.orders.index', ['unit_id' => $originalUnit]))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
        $this->get(route('morador.orders.show', $order))->assertNotFound();
        $this->actingAs($remainingResident)->get(route('morador.orders.index'))
            ->assertInertia(fn (Assert $page) => $page->where('orders.data.0.id', $order->id)->where('orders.data.0.unit.id', $originalUnit));
        $this->get(route('morador.orders.show', $order))->assertInertia(fn (Assert $page) => $page->where('order.unit.id', $originalUnit));
        $this->actingAs(User::factory()->porteiro()->create())->get(route('portaria.orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page->where('order.unit.id', $originalUnit));
        $this->assertSame($originalUnit, $order->fresh()->unit_id);
    }

    public function test_unit_isolation_and_no_unit_do_not_leak_details(): void
    {
        $order = Order::factory()->create();
        foreach ([User::factory()->create(), User::factory()->create(['unit_id' => null])] as $user) {
            $this->actingAs($user)->get(route('morador.orders.index', ['unit_id' => $order->unit_id, 'resident_id' => $order->resident_id]))
                ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
            $this->get(route('morador.orders.show', $order))->assertNotFound();
        }
    }

    public function test_portaria_filters_unit_status_period_search_and_pagination(): void
    {
        $resident = User::factory()->create();
        $orders = Order::factory()->count(11)->create([
            'unit_id' => $resident->unit_id, 'resident_id' => $resident->id,
            'status' => OrderStatus::PickedUp, 'picked_up_at' => '2026-08-10 12:00:00',
            'description' => 'Livros especiais', 'sender' => 'Loja Central', 'tracking_code' => 'BR123',
        ]);
        Order::factory()->create(['status' => OrderStatus::PickedUp, 'picked_up_at' => '2026-08-10 12:00:00', 'description' => 'Livros especiais']);
        $filters = ['unit_id' => $resident->unit_id, 'status' => 'picked_up', 'date_from' => '2026-08-10', 'date_to' => '2026-08-10'];
        $this->actingAs(User::factory()->porteiro()->create());
        foreach (['livros', 'central', 'br123'] as $search) {
            $this->get(route('portaria.order-history.index', $filters + ['search' => $search]))
                ->assertInertia(fn (Assert $page) => $page->component('portaria/order-history/index')
                    ->where('orders.total', 11)->has('orders.data', 10)->where('orders.data.0.id', $orders->last()->id));
        }
        $this->get(route('portaria.order-history.index', $filters + ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $orders->first()->id)
                ->where('filters.status', 'picked_up')->where('filters.unit_id', $resident->unit_id));
        $this->get(route('portaria.order-history.index', ['search' => '%_']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
        $this->actingAs($resident)->get(route('morador.orders.index', ['status' => 'picked_up', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('filters.status', 'picked_up'));
    }

    public function test_invalid_filters_and_null_operational_dates(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::ReceivedAtGate, 'received_at' => null]);
        $this->actingAs($order->resident)->get(route('morador.orders.index', ['status' => 'invalid', 'date_from' => '2026-08-10', 'date_to' => '2026-08-09', 'page' => 0]))
            ->assertSessionHasErrors(['status', 'date_to', 'page']);
        $this->get(route('morador.orders.index', ['date_from' => 'invalid']))->assertSessionHasErrors('date_from');
        $this->get(route('morador.orders.index', ['date_from' => '2026-01-01']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
        $this->get(route('morador.orders.show', $order))->assertInertia(fn (Assert $page) => $page->where('order.status_date', null));
        $this->actingAs(User::factory()->porteiro()->create())->get(route('portaria.order-history.index', ['unit_id' => 99999]))->assertSessionHasErrors('unit_id');
    }

    public function test_deleted_and_missing_orders_are_not_exposed(): void
    {
        $order = Order::factory()->create();
        $order->delete();
        foreach (['morador' => $order->resident, 'portaria' => User::factory()->porteiro()->create()] as $prefix => $user) {
            $this->actingAs($user)->get(route($prefix.'.orders.show', $order))->assertNotFound();
            $this->get(route($prefix.'.orders.show', 99999))->assertNotFound();
            $this->get(route($prefix === 'morador' ? 'morador.orders.index' : 'portaria.order-history.index'))
                ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
        }
    }

    public function test_history_routes_enforce_authentication_role_activity_and_verification(): void
    {
        $order = Order::factory()->create();
        $urls = [route('morador.orders.show', $order), route('portaria.orders.show', $order), route('portaria.order-history.index')];
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->actingAs(User::factory()->admin()->create());
        foreach ($urls as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->actingAs($order->resident)->get(route('portaria.order-history.index'))->assertForbidden();
        $this->get(route('portaria.orders.show', $order))->assertForbidden();
        $this->actingAs(User::factory()->porteiro()->create())->get(route('morador.orders.show', $order))->assertForbidden();
        foreach (['morador', 'portaria'] as $prefix) {
            $role = $prefix === 'morador' ? 'morador' : 'porteiro';
            $this->actingAs(User::factory()->inactive()->create(['role' => $role]))->get(route($prefix.'.orders.show', $order))->assertRedirect(route('login'));
            $this->actingAs(User::factory()->unverified()->create(['role' => $role]))->get(route($prefix.'.orders.show', $order))->assertRedirect(route('verification.notice'));
        }
    }
}
