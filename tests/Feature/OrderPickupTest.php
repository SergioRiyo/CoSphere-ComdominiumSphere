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

class OrderPickupTest extends TestCase
{
    use RefreshDatabase;

    private function receivedOrder(array $attributes = []): Order
    {
        return Order::factory()->create($attributes + ['status' => OrderStatus::ReceivedAtGate, 'picked_up_by_id' => null, 'picked_up_at' => null]);
    }

    public static function actors(): array
    {
        return [['porteiro'], ['destinatario'], ['morador']];
    }

    #[DataProvider('actors')]
    public function test_authorized_profiles_confirm_and_preserve_history(string $profile): void
    {
        $this->travelTo(now()->startOfSecond());
        $order = $this->receivedOrder();
        $actor = match ($profile) {
            'porteiro' => User::factory()->porteiro()->create(),
            'destinatario' => $order->resident,
            default => User::factory()->create(['unit_id' => $order->unit_id]),
        };
        $prefix = $profile === 'porteiro' ? 'portaria' : 'morador';
        $this->actingAs($actor)->from(route($prefix.'.orders.index'))
            ->patch(route($prefix.'.orders.pickup', $order))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'unit_id' => $order->unit_id, 'picked_up_by_id' => $actor->id, 'picked_up_at' => now(), 'status' => 'picked_up', 'deleted_at' => null]);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('notifications', 0);
        $this->actingAs($order->resident)->get(route('morador.orders.index'))
            ->assertInertia(fn (Assert $page) => $page->where('orders.data.0.id', $order->id)
                ->where('orders.data.0.status', 'picked_up')->where('orders.data.0.can_pickup', false)
                ->where('orders.data.0.pickup_confirmed_by', $actor->name)
                ->where('orders.data.0.picked_up_at', now()->toISOString()));
        $this->actingAs(User::factory()->porteiro()->create())->get(route('portaria.orders.index'))
            ->assertInertia(fn (Assert $page) => $page->has('receivedOrders.data', 0));
    }

    public static function firstActors(): array
    {
        return [['portaria'], ['morador']];
    }

    #[DataProvider('firstActors')]
    public function test_cross_profile_repetition_preserves_first_operator_and_timestamp(string $first): void
    {
        $order = $this->receivedOrder();
        $doorman = User::factory()->porteiro()->create();
        $actor = $first === 'portaria' ? $doorman : $order->resident;
        $second = $first === 'portaria' ? 'morador' : 'portaria';
        $this->actingAs($actor)->patch(route($first.'.orders.pickup', $order))->assertSessionHasNoErrors();
        $before = $order->fresh()->getRawOriginal();
        $this->travel(10)->minutes();
        $this->actingAs($second === 'portaria' ? $doorman : $order->resident)
            ->patch(route($second.'.orders.pickup', $order))->assertSessionHasErrors('order');
        $this->assertSame($before, $order->fresh()->getRawOriginal());
    }

    public function test_other_unit_and_wrong_profiles_are_blocked(): void
    {
        $order = $this->receivedOrder();
        $this->actingAs(User::factory()->create())->patch(route('morador.orders.pickup', $order))->assertNotFound();
        $this->actingAs(User::factory()->create(['unit_id' => null]))->patch(route('morador.orders.pickup', $order))->assertNotFound();
        $this->actingAs(User::factory()->admin()->create(['unit_id' => $order->unit_id]));
        foreach (['morador', 'portaria'] as $prefix) {
            $this->patch(route($prefix.'.orders.pickup', $order))->assertForbidden();
        }
        $this->actingAs($order->resident)->patch(route('portaria.orders.pickup', $order))->assertForbidden();
        $this->actingAs(User::factory()->porteiro()->create())->patch(route('morador.orders.pickup', $order))->assertForbidden();
        $this->assertSame(OrderStatus::ReceivedAtGate, $order->fresh()->status);
    }

    public function test_only_received_orders_can_be_confirmed(): void
    {
        foreach ([OrderStatus::WaitingDelivery, OrderStatus::PickedUp, OrderStatus::Cancelled] as $status) {
            $order = Order::factory()->create(['status' => $status]);
            $before = $order->fresh()->getRawOriginal();
            $this->actingAs($order->resident)->patch(route('morador.orders.pickup', $order))->assertSessionHasErrors('order');
            $this->actingAs(User::factory()->porteiro()->create())->patch(route('portaria.orders.pickup', $order))->assertSessionHasErrors('order');
            $this->assertSame($before, $order->fresh()->getRawOriginal());
        }
    }

    public function test_recipient_move_or_deactivation_blocks_both_paths(): void
    {
        foreach ([['unit_id' => Unit::factory()->create()->id], ['is_active' => false], ['role' => 'admin']] as $change) {
            $order = $this->receivedOrder();
            $coResident = User::factory()->create(['unit_id' => $order->unit_id]);
            $order->resident->update($change);
            $this->actingAs($coResident)->patch(route('morador.orders.pickup', $order))->assertSessionHasErrors();
            $this->actingAs(User::factory()->porteiro()->create())->patch(route('portaria.orders.pickup', $order))->assertSessionHasErrors();
            $this->assertNull($order->fresh()->picked_up_at);
            $this->assertNull($order->fresh()->picked_up_by_id);
        }
    }

    public function test_operational_fields_are_rejected_for_both_routes(): void
    {
        $order = $this->receivedOrder();
        foreach (['morador' => $order->resident, 'portaria' => User::factory()->porteiro()->create()] as $prefix => $user) {
            $this->actingAs($user);
            foreach (['status', 'unit_id', 'resident_id', 'picked_up_at', 'picked_up_by_id', 'received_at', 'received_by_id', 'description', 'carrier', 'sender', 'tracking_code', 'expected_delivery_date'] as $field) {
                $this->patch(route($prefix.'.orders.pickup', $order), [$field => '123'])->assertSessionHasErrors($field);
            }
        }
        $this->assertNull($order->fresh()->picked_up_at);
    }

    public function test_authentication_activity_verification_missing_and_deleted_records(): void
    {
        $order = $this->receivedOrder();
        foreach (['morador', 'portaria'] as $prefix) {
            $this->patch(route($prefix.'.orders.pickup', $order))->assertRedirect(route('login'));
        }
        foreach (['morador' => 'morador', 'portaria' => 'porteiro'] as $prefix => $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => false]))
                ->patch(route($prefix.'.orders.pickup', $order))->assertRedirect(route('login'));
            $this->actingAs(User::factory()->unverified()->create(['role' => $role]))
                ->patch(route($prefix.'.orders.pickup', $order))->assertRedirect(route('verification.notice'));
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->patch(route($prefix.'.orders.pickup', 99999))->assertNotFound();
        }
        $order->delete();
        $this->actingAs($order->resident)->patch(route('morador.orders.pickup', $order))->assertNotFound();
        $this->actingAs(User::factory()->porteiro()->create())->patch(route('portaria.orders.pickup', $order))->assertNotFound();
    }

    public function test_portaria_received_list_filters_and_paginates_independently(): void
    {
        $unit = Unit::factory()->create();
        for ($i = 0; $i < 11; $i++) {
            $this->receivedOrder(['unit_id' => $unit->id, 'sender' => 'Livraria', 'tracking_code' => 'BR123']);
        }
        $this->receivedOrder();
        Order::factory()->create(['unit_id' => $unit->id, 'status' => OrderStatus::WaitingDelivery]);
        $deleted = $this->receivedOrder(['unit_id' => $unit->id]);
        $deleted->delete();
        $this->actingAs(User::factory()->porteiro()->create());
        foreach (['Livraria', 'BR123'] as $search) {
            $this->get(route('portaria.orders.index', ['unit_id' => $unit->id, 'search' => $search]))
                ->assertInertia(fn (Assert $page) => $page->has('receivedOrders.data', 10)->where('receivedOrders.total', 11)->where('receivedOrders.data.0.can_pickup', true));
        }
        $this->get(route('portaria.orders.index', ['unit_id' => $unit->id, 'received_page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('receivedOrders.data', 1)->where('orders.current_page', 1));
    }
}
