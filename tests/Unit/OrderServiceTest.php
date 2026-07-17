<?php

namespace Tests\Unit;

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Unit;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderService = app(OrderService::class);
    }

    public function test_deve_cadastrar_encomenda_prevista(): void
    {
        $unit = Unit::factory()->create();
        $resident = User::factory()->create([
            'unit_id' => $unit->id,
        ]);

        $order = $this->orderService->createExpectedByResident([
            'unit_id' => $unit->id,
            'tracking_code' => 'BR123456789AA',
            'sender' => 'Loja Centro',
            'carrier' => 'Correios',
            'description' => 'Caixa pequena',
            'expected_delivery_date' => now()->addDays(2)->toDateString(),
        ], $resident);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame(OrderStatus::WaitingDelivery, $order->status);
        $this->assertNull($order->received_by_id);
        $this->assertNull($order->picked_up_by_id);
        $this->assertNull($order->received_at);
        $this->assertNull($order->picked_up_at);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
            'tracking_code' => 'BR123456789AA',
            'sender' => 'Loja Centro',
            'carrier' => 'Correios',
            'description' => 'Caixa pequena',
            'status' => OrderStatus::WaitingDelivery->value,
        ]);
    }

    public function test_deve_cadastrar_encomenda_nao_prevista(): void
    {
        $unit = Unit::factory()->create();
        $resident = User::factory()->create([
            'unit_id' => $unit->id,
        ]);
        $doorman = User::factory()->create([
            'role' => 'porteiro',
        ]);

        $order = $this->orderService->createUnexpectedByDoorman([
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
            'tracking_code' => 'BR987654321AA',
            'sender' => 'Marketplace XPTO',
            'carrier' => 'Jadlog',
            'description' => 'Pacote surpresa',
        ], $doorman);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame(OrderStatus::ReceivedAtGate, $order->status);
        $this->assertSame($doorman->id, $order->received_by_id);
        $this->assertNull($order->picked_up_by_id);
        $this->assertNull($order->expected_delivery_date);
        $this->assertNotNull($order->received_at);
        $this->assertNull($order->picked_up_at);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
            'received_by_id' => $doorman->id,
            'tracking_code' => 'BR987654321AA',
            'status' => OrderStatus::ReceivedAtGate->value,
        ]);

        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $resident->id,
            'title' => 'Encomenda recebida na portaria',
            'type' => NotificationType::Package->value,
        ]);
    }

    public function test_deve_registrar_recebimento_na_portaria(): void
    {
        $unit = Unit::factory()->create();
        $resident = User::factory()->create([
            'unit_id' => $unit->id,
        ]);
        $doorman = User::factory()->create([
            'role' => 'porteiro',
        ]);
        $order = Order::factory()->create([
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
            'received_by_id' => null,
            'picked_up_by_id' => null,
            'received_at' => null,
            'picked_up_at' => null,
            'status' => OrderStatus::WaitingDelivery,
        ]);

        $receivedOrder = $this->orderService->receive($order, $doorman);

        $this->assertSame($doorman->id, $receivedOrder->received_by_id);
        $this->assertNotNull($receivedOrder->received_at);
        $this->assertNull($receivedOrder->picked_up_at);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'received_by_id' => $doorman->id,
            'status' => OrderStatus::ReceivedAtGate->value,
        ]);

        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $resident->id,
            'title' => 'Encomenda recebida na portaria',
            'type' => NotificationType::Package->value,
        ]);
    }

    public function test_deve_alterar_status_para_recebida_na_portaria(): void
    {
        $unit = Unit::factory()->create();
        $resident = User::factory()->create([
            'unit_id' => $unit->id,
        ]);
        $doorman = User::factory()->create([
            'role' => 'porteiro',
        ]);
        $order = Order::factory()->create([
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
            'received_by_id' => null,
            'picked_up_by_id' => null,
            'received_at' => null,
            'picked_up_at' => null,
            'status' => OrderStatus::WaitingDelivery,
        ]);

        $receivedOrder = $this->orderService->receive($order, $doorman);

        $this->assertSame(OrderStatus::ReceivedAtGate, $receivedOrder->status);
    }

    public function test_deve_registrar_retirada_da_encomenda(): void
    {
        $unit = Unit::factory()->create();
        $resident = User::factory()->create([
            'unit_id' => $unit->id,
        ]);
        $pickupUser = User::factory()->create([
            'unit_id' => $unit->id,
        ]);
        $order = Order::factory()->create([
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
            'received_by_id' => User::factory(),
            'picked_up_by_id' => null,
            'received_at' => now()->subHour(),
            'picked_up_at' => null,
            'status' => OrderStatus::ReceivedAtGate,
        ]);

        $pickedUpOrder = $this->orderService->pickup($order, $pickupUser);

        $this->assertSame($pickupUser->id, $pickedUpOrder->picked_up_by_id);
        $this->assertNotNull($pickedUpOrder->picked_up_at);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'picked_up_by_id' => $pickupUser->id,
            'status' => OrderStatus::PickedUp->value,
        ]);
    }

    public function test_deve_alterar_status_para_retirada(): void
    {
        $unit = Unit::factory()->create();
        $resident = User::factory()->create([
            'unit_id' => $unit->id,
        ]);
        $pickupUser = User::factory()->create([
            'unit_id' => $unit->id,
        ]);
        $order = Order::factory()->create([
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
            'received_by_id' => User::factory(),
            'picked_up_by_id' => null,
            'received_at' => now()->subHour(),
            'picked_up_at' => null,
            'status' => OrderStatus::ReceivedAtGate,
        ]);

        $pickedUpOrder = $this->orderService->pickup($order, $pickupUser);

        $this->assertSame(OrderStatus::PickedUp, $pickedUpOrder->status);
    }

    public function test_deve_cancelar_encomenda(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::WaitingDelivery,
            'received_by_id' => null,
            'picked_up_by_id' => null,
            'received_at' => null,
            'picked_up_at' => null,
        ]);

        $cancelledOrder = $this->orderService->cancel($order);

        $this->assertSame(OrderStatus::Cancelled, $cancelledOrder->status);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Cancelled->value,
        ]);
    }
}
