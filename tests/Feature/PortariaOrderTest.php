<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Unit;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PortariaOrderTest extends TestCase
{
    use RefreshDatabase;

    private function expectedOrder(array $attributes = []): Order
    {
        return Order::factory()->create($attributes + [
            'status' => OrderStatus::WaitingDelivery,
            'received_by_id' => null,
            'received_at' => null,
            'picked_up_by_id' => null,
            'picked_up_at' => null,
        ]);
    }

    public function test_search_filters_expected_orders_and_scopes_resident_options(): void
    {
        $order = $this->expectedOrder(['sender' => 'Loja Central', 'tracking_code' => 'BR123']);
        $this->expectedOrder(['sender' => 'Outra Loja', 'tracking_code' => 'BR456']);
        $this->expectedOrder(['unit_id' => $order->unit_id, 'resident_id' => $order->resident_id, 'sender' => 'Loja Central', 'status' => OrderStatus::ReceivedAtGate]);
        $deleted = $this->expectedOrder(['unit_id' => $order->unit_id, 'resident_id' => $order->resident_id, 'sender' => 'Loja Central']);
        $deleted->delete();
        User::factory()->inactive()->create(['unit_id' => $order->unit_id]);
        User::factory()->admin()->create(['unit_id' => $order->unit_id]);
        $this->actingAs(User::factory()->porteiro()->create());

        foreach (['br123', 'central', ''] as $search) {
            $this->get(route('portaria.orders.index', ['unit_id' => $order->unit_id, 'search' => $search]))
                ->assertInertia(fn (Assert $page) => $page->component('portaria/orders/index')
                    ->has('orders.data', 1)->where('orders.data.0.id', $order->id)
                    ->where('orders.data.0.resident_name', $order->resident->name)
                    ->where('orders.data.0.can_receive', true)
                    ->has('residentOptions', 1)->where('residentOptions.0.id', $order->resident_id)
                    ->missing('residentOptions.0.email')->missing('residentOptions.0.cpf'));
        }
        $this->get(route('portaria.orders.index', ['search' => 'BR123']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->has('residentOptions', 0));
        $this->get(route('portaria.orders.index', ['search' => '%_']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
        $this->expectedOrder(['sender' => 'Loja 100%_!']);
        $this->get(route('portaria.orders.index', ['search' => '%_!']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
    }

    public function test_search_is_paginated_and_validated(): void
    {
        $last = null;
        for ($i = 0; $i < 11; $i++) {
            $last = $this->expectedOrder();
        }
        $this->actingAs(User::factory()->porteiro()->create())
            ->get(route('portaria.orders.index'))->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 10)->where('orders.total', 11)->where('orders.data.0.id', $last->id));
        $this->get(route('portaria.orders.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
        $this->get(route('portaria.orders.index', ['unit_id' => 99999, 'page' => 0, 'search' => str_repeat('x', 256)]))
            ->assertSessionHasErrors(['unit_id', 'page', 'search']);
    }

    #[DataProvider('trackingCodes')]
    public function test_expected_receipt_updates_same_record_and_notifies_once(?string $trackingCode): void
    {
        $this->freezeTime();
        $doorman = User::factory()->porteiro()->create();
        $order = $this->expectedOrder(['tracking_code' => $trackingCode]);
        $this->actingAs($doorman)->from(route('portaria.orders.index'))
            ->patch(route('portaria.orders.receive', $order))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id, 'unit_id' => $order->unit_id, 'resident_id' => $order->resident_id,
            'received_by_id' => $doorman->id, 'received_at' => now(),
            'status' => OrderStatus::ReceivedAtGate->value,
            'picked_up_at' => null, 'picked_up_by_id' => null,
        ]);
        $notification = Notification::query()->sole();
        $this->assertSame($order->resident_id, $notification->recipient_id);
        $this->assertFalse($notification->is_read);
        $this->travel(5)->minutes();
        $this->actingAs(User::factory()->porteiro()->create())->patch(route('portaria.orders.receive', $order))
            ->assertSessionHasErrors('order');
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame($doorman->id, $order->fresh()->received_by_id);
        $this->assertTrue($order->fresh()->received_at->equalTo($notification->sent_at));
        $this->actingAs($order->resident)->get(route('morador.notifications.index'))
            ->assertInertia(fn (Assert $page) => $page->where('notifications.data.0.id', $notification->id));
    }

    public static function trackingCodes(): array
    {
        return ['with tracking' => ['BR123'], 'without tracking' => [null]];
    }

    #[DataProvider('trackingCodes')]
    public function test_unexpected_receipt_uses_session_doorman_and_notifies_recipient(?string $trackingCode): void
    {
        $resident = User::factory()->create();
        $doorman = User::factory()->porteiro()->create();
        $this->actingAs($doorman)->post(route('portaria.orders.store'), [
            'unit_id' => $resident->unit_id, 'resident_id' => $resident->id,
            'description' => 'Caixa de livros', 'tracking_code' => $trackingCode,
        ])->assertSessionHasNoErrors()->assertRedirect(route('portaria.orders.index', ['unit_id' => $resident->unit_id]));
        $order = Order::query()->sole();
        $this->assertSame(OrderStatus::ReceivedAtGate, $order->status);
        $this->assertSame($doorman->id, $order->received_by_id);
        $this->assertNotNull($order->received_at);
        $this->assertNull($order->expected_delivery_date);
        $this->assertNull($order->picked_up_at);
        $this->assertSame($trackingCode, $order->tracking_code);
        $this->assertSame($resident->id, Notification::query()->sole()->recipient_id);
        $this->patch(route('portaria.orders.receive', $order))->assertSessionHasErrors('order');
        $this->assertDatabaseCount('notifications', 1);
        $this->actingAs($resident)->get(route('morador.notifications.index'))
            ->assertInertia(fn (Assert $page) => $page->has('notifications.data', 1)->where('notifications.data.0.type', 'package'));
    }

    public function test_moved_or_unavailable_recipient_blocks_receipt_without_changes(): void
    {
        $this->actingAs(User::factory()->porteiro()->create());
        foreach ([['unit_id' => Unit::factory()->create()->id], ['unit_id' => null], ['is_active' => false], ['role' => UserRole::Admin]] as $change) {
            $order = $this->expectedOrder();
            $order->resident->update($change);
            $this->get(route('portaria.orders.index', ['unit_id' => $order->unit_id]))
                ->assertInertia(fn (Assert $page) => $page->where('orders.data.0.can_receive', false));
            $this->patch(route('portaria.orders.receive', $order))->assertSessionHasErrors();
            $this->assertSame(OrderStatus::WaitingDelivery, $order->fresh()->status);
            $this->assertNull($order->fresh()->received_at);
            $this->assertDatabaseCount('notifications', 0);
        }
    }

    public function test_received_withdrawn_cancelled_deleted_and_missing_records_are_rejected(): void
    {
        $this->actingAs(User::factory()->porteiro()->create());
        foreach ([OrderStatus::ReceivedAtGate, OrderStatus::PickedUp, OrderStatus::Cancelled] as $status) {
            $order = Order::factory()->create(['status' => $status]);
            $before = $order->fresh()->getRawOriginal();
            $this->patch(route('portaria.orders.receive', $order))->assertSessionHasErrors('order');
            $this->assertSame($before, $order->fresh()->getRawOriginal());
        }
        $deleted = $this->expectedOrder();
        $deleted->delete();
        $this->patch(route('portaria.orders.receive', $deleted))->assertNotFound();
        $this->patch(route('portaria.orders.receive', 99999))->assertNotFound();
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_unexpected_receipt_rejects_mismatched_unit_and_invalid_residents(): void
    {
        $resident = User::factory()->create();
        $this->actingAs(User::factory()->porteiro()->create())->post(route('portaria.orders.store'), [
            'unit_id' => Unit::factory()->create()->id, 'resident_id' => $resident->id, 'description' => 'Livros',
        ])->assertSessionHasErrors('unit_id');
        foreach ([User::factory()->inactive()->create(), User::factory()->admin()->create(), User::factory()->create(['unit_id' => null])] as $invalid) {
            $this->post(route('portaria.orders.store'), ['unit_id' => $resident->unit_id, 'resident_id' => $invalid->id, 'description' => 'Livros'])
                ->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_validation_rejects_invalid_fields_and_operational_tampering(): void
    {
        $resident = User::factory()->create();
        $valid = ['unit_id' => $resident->unit_id, 'resident_id' => $resident->id, 'description' => 'Livros'];
        $this->actingAs(User::factory()->porteiro()->create());
        $this->post(route('portaria.orders.store'), [])->assertSessionHasErrors(['unit_id', 'resident_id', 'description']);
        foreach (['description' => ' ', 'carrier' => ['invalid'], 'sender' => str_repeat('x', 256), 'tracking_code' => str_repeat('x', 256), 'unit_id' => 99999, 'resident_id' => 99999] as $field => $value) {
            $this->post(route('portaria.orders.store'), [$field => $value] + $valid)->assertSessionHasErrors($field);
        }
        foreach (['status', 'received_by_id', 'picked_up_by_id', 'received_at', 'picked_up_at', 'expected_delivery_date'] as $field) {
            $this->post(route('portaria.orders.store'), $valid + [$field => '123'])->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('orders', 0);
        $order = $this->expectedOrder();
        foreach (['unit_id', 'resident_id', 'description', 'carrier', 'sender', 'tracking_code', 'status', 'received_by_id', 'picked_up_by_id', 'received_at', 'picked_up_at', 'expected_delivery_date'] as $field) {
            $this->patch(route('portaria.orders.receive', $order), [$field => '123'])->assertSessionHasErrors($field);
        }
        $this->assertSame(OrderStatus::WaitingDelivery, $order->fresh()->status);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_routes_require_active_verified_doorman(): void
    {
        $order = $this->expectedOrder();
        $requests = [['get', route('portaria.orders.index')], ['post', route('portaria.orders.store')], ['patch', route('portaria.orders.receive', $order)]];
        foreach ($requests as [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('login'));
        }
        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user);
            foreach ($requests as [$method, $url]) {
                $this->{$method}($url)->assertForbidden();
            }
        }
        $inactive = User::factory()->porteiro()->inactive()->create();
        foreach ($requests as [$method, $url]) {
            $this->actingAs($inactive)->{$method}($url)->assertRedirect(route('login'));
        }
        $this->actingAs(User::factory()->porteiro()->unverified()->create());
        foreach ($requests as [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('verification.notice'));
        }
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_notification_failure_rolls_back_both_receipt_paths(): void
    {
        $order = $this->expectedOrder();
        $this->mock(NotificationService::class)->shouldReceive('create')->twice()->andThrow(new RuntimeException('Notification failure'));
        $this->actingAs(User::factory()->porteiro()->create());
        $this->patch(route('portaria.orders.receive', $order))->assertServerError();
        $this->assertSame(OrderStatus::WaitingDelivery, $order->fresh()->status);
        $this->assertNull($order->fresh()->received_at);
        $this->post(route('portaria.orders.store'), [
            'unit_id' => $order->unit_id, 'resident_id' => $order->resident_id, 'description' => 'Livros',
        ])->assertServerError();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('notifications', 0);
    }
}
