<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $status = fake()->randomElement(OrderStatus::cases());

        $receivedAt = in_array($status, [
            OrderStatus::ReceivedAtGate,
            OrderStatus::PickedUp,
        ], true)
            ? fake()->dateTimeBetween('-15 days', 'now')
            : null;

        $pickedUpAt = $status === OrderStatus::PickedUp
            ? fake()->dateTimeBetween($receivedAt ?? '-10 days', 'now')
            : null;

        return [
            'unit_id' => Unit::query()->inRandomOrder()->value('id') ?? Unit::factory(),
            'resident_id' => User::query()->inRandomOrder()->value('id') ?? User::factory(),

            'received_by_id' => $receivedAt
                ? (User::query()->inRandomOrder()->value('id') ?? User::factory())
                : null,

            'picked_up_by_id' => $pickedUpAt
                ? (User::query()->inRandomOrder()->value('id') ?? User::factory())
                : null,

            'tracking_code' => fake()->optional()->bothify('BR#########??'),
            'sender' => fake()->optional()->company(),
            'carrier' => fake()->optional()->randomElement([
                'Correios',
                'Jadlog',
                'Loggi',
                'Mercado Livre',
                'Amazon Logistics',
            ]),
            'description' => fake()->optional()->sentence(),
            'expected_delivery_date' => fake()->optional()->dateTimeBetween('now', '+10 days'),

            'received_at' => $receivedAt,
            'picked_up_at' => $pickedUpAt,
            'status' => $status->value,
        ];
    }
}
