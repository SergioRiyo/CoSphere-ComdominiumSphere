<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Unit;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResidentExpectedOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_can_create_and_consult_an_expected_order(): void
    {
        $resident = User::factory()->create();
        $data = ['description' => 'Caixa de livros', 'carrier' => 'Correios', 'sender' => 'Livraria', 'tracking_code' => 'BR123'];

        $this->actingAs($resident)->post(route('morador.orders.store'), $data)
            ->assertSessionHasNoErrors()->assertRedirect(route('morador.orders.index'));

        $order = Order::query()->sole();
        $this->assertDatabaseHas('orders', $data + [
            'resident_id' => $resident->id, 'unit_id' => $resident->unit_id,
            'status' => OrderStatus::WaitingDelivery->value,
            'received_by_id' => null, 'picked_up_by_id' => null,
            'received_at' => null, 'picked_up_at' => null, 'expected_delivery_date' => null,
        ]);
        $this->assertTrue($resident->unit->orders()->whereKey($order->id)->exists());
        $this->assertDatabaseCount('notifications', 0);
        $this->get(route('morador.orders.index'))->assertInertia(fn (Assert $page) => $page
            ->component('morador/orders/index')
            ->where('unit.id', $resident->unit_id)
            ->where('orders.data.0.id', $order->id)
            ->where('orders.data.0.description', 'Caixa de livros')
            ->where('orders.data.0.status_label', 'Aguardando entrega'));
    }

    public function test_optional_fields_can_be_omitted_or_blank(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([[], ['carrier' => ' ', 'sender' => '', 'tracking_code' => null]] as $optional) {
            $this->post(route('morador.orders.store'), ['description' => '  Livros  '] + $optional)->assertSessionHasNoErrors();
            $order = Order::query()->latest('id')->firstOrFail();
            $this->assertSame('Livros', $order->description);
            $this->assertNull($order->carrier);
            $this->assertNull($order->sender);
            $this->assertNull($order->tracking_code);
        }
    }

    #[DataProvider('invalidData')]
    public function test_invalid_fields_are_rejected(array $data, string $field): void
    {
        $this->actingAs(User::factory()->create())->post(route('morador.orders.store'), $data)
            ->assertSessionHasErrors($field);
        $this->assertDatabaseCount('orders', 0);
    }

    public static function invalidData(): array
    {
        $cases = [
            'missing description' => [[], 'description'],
            'blank description' => [['description' => '   '], 'description'],
            'long description' => [['description' => str_repeat('a', 5001)], 'description'],
            'invalid description' => [['description' => ['invalid']], 'description'],
        ];
        foreach (['carrier', 'sender', 'tracking_code'] as $field) {
            $cases[$field.' too long'] = [['description' => 'Livros', $field => str_repeat('a', 256)], $field];
            $cases[$field.' invalid type'] = [['description' => 'Livros', $field => ['invalid']], $field];
        }
        foreach (['unit_id', 'resident_id', 'status', 'received_by_id', 'picked_up_by_id', 'received_at', 'picked_up_at', 'expected_delivery_date'] as $field) {
            $cases[$field.' forbidden'] = [['description' => 'Livros', $field => '123'], $field];
            $cases[$field.' null forbidden'] = [['description' => 'Livros', $field => null], $field];
        }

        return $cases;
    }

    public function test_forged_unit_and_resident_are_rejected(): void
    {
        $resident = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($resident)->post(route('morador.orders.store'), [
            'description' => 'Livros', 'unit_id' => $other->unit_id, 'resident_id' => $other->id,
        ])->assertSessionHasErrors(['unit_id', 'resident_id']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_resident_without_unit_cannot_create_and_receives_empty_list(): void
    {
        Order::factory()->create();
        $this->actingAs(User::factory()->create(['unit_id' => null]))
            ->post(route('morador.orders.store'), ['description' => 'Livros'])->assertSessionHasErrors('resident');
        $this->assertDatabaseCount('orders', 1);
        $this->get(route('morador.orders.index'))->assertInertia(fn (Assert $page) => $page
            ->where('unit', null)->has('orders.data', 0));
    }

    public function test_list_is_scoped_to_unit_and_paginated_even_with_forged_query(): void
    {
        $resident = User::factory()->create();
        $coResident = User::factory()->create(['unit_id' => $resident->unit_id]);
        $other = Order::factory()->create();
        $orders = Order::factory()->count(11)->create(['unit_id' => $resident->unit_id, 'resident_id' => $coResident->id]);
        $deleted = Order::factory()->create(['unit_id' => $resident->unit_id, 'resident_id' => $resident->id]);
        $deleted->delete();

        $this->actingAs($resident)->get(route('morador.orders.index', ['unit_id' => $other->unit_id]))
            ->assertInertia(fn (Assert $page) => $page->where('orders.total', 11)
                ->has('orders.data', 10)->where('orders.data.0.id', $orders->last()->id));
        $this->get(route('morador.orders.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $orders->first()->id));
    }

    public function test_routes_require_active_verified_resident(): void
    {
        $index = route('morador.orders.index');
        $store = route('morador.orders.store');
        $data = ['description' => 'Livros'];
        $this->get($index)->assertRedirect(route('login'));
        $this->post($store, $data)->assertRedirect(route('login'));
        foreach ([User::factory()->admin()->create(), User::factory()->porteiro()->create()] as $user) {
            $this->actingAs($user)->get($index)->assertForbidden();
            $this->post($store, $data)->assertForbidden();
        }
        $inactive = User::factory()->inactive()->create();
        $this->actingAs($inactive)->get($index)->assertRedirect(route('login'));
        $this->actingAs($inactive)->post($store, $data)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())->get($index)->assertRedirect(route('verification.notice'));
        $this->post($store, $data)->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_service_derives_current_unit_and_forces_operational_fields(): void
    {
        $resident = User::factory()->create();
        $unit = Unit::factory()->create();
        User::query()->whereKey($resident->id)->update(['unit_id' => $unit->id]);
        $order = app(OrderService::class)->createExpectedByResident([
            'description' => 'Livros', 'resident_id' => 999,
            'status' => 'picked_up', 'received_by_id' => 999, 'picked_up_by_id' => 999,
            'received_at' => now(), 'picked_up_at' => now(),
        ], $resident);
        $this->assertSame($unit->id, $order->unit_id);
        $this->assertSame($resident->id, $order->resident_id);
        $this->assertSame(OrderStatus::WaitingDelivery, $order->status);
        $this->assertNull($order->received_by_id);
        $this->assertNull($order->picked_up_by_id);
        $this->assertNull($order->received_at);
        $this->assertNull($order->picked_up_at);
    }

    public function test_service_still_rejects_explicit_mismatched_unit(): void
    {
        $this->expectException(ValidationException::class);
        app(OrderService::class)->createExpectedByResident([
            'description' => 'Livros', 'unit_id' => Unit::factory()->create()->id,
        ], User::factory()->create());
    }
}
