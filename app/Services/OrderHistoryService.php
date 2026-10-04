<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderHistoryService
{
    private function accessibleOrders(User $user): Builder
    {
        abort_unless($user->is_active && in_array($user->role, [UserRole::Morador, UserRole::Porteiro], true), 403);
        $query = Order::query()->with([
            'unit:id,block,number', 'resident:id,name,unit_id,role,is_active',
            'receivedBy:id,name', 'pickupConfirmedBy:id,name',
        ]);
        if ($user->role === UserRole::Morador) {
            $query->where('orders.unit_id', $user->unit_id)->whereNotNull('orders.unit_id');
        }

        return $query;
    }

    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        $query = $this->accessibleOrders($user);
        if ($user->role === UserRole::Porteiro && ! empty($filters['unit_id'])) {
            $query->where('orders.unit_id', $filters['unit_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['search']) && trim($filters['search']) !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['search'])).'%';
            $query->where(function (Builder $query) use ($pattern): void {
                $query->whereRaw("LOWER(tracking_code) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(sender) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(description) LIKE LOWER(?) ESCAPE '!'", [$pattern]);
            });
        }
        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $from = ! empty($filters['date_from']) ? CarbonImmutable::parse($filters['date_from'], config('app.timezone'))->startOfDay() : null;
            $until = ! empty($filters['date_to']) ? CarbonImmutable::parse($filters['date_to'], config('app.timezone'))->addDay()->startOfDay() : null;
            $query->where(function (Builder $query) use ($from, $until): void {
                foreach (OrderStatus::cases() as $status) {
                    $query->orWhere(function (Builder $query) use ($status, $from, $until): void {
                        $column = Order::statusDateColumn($status);
                        $query->where('status', $status);
                        if ($from !== null) {
                            $query->where($column, '>=', $from);
                        }
                        if ($until !== null) {
                            $query->where($column, '<', $until);
                        }
                    });
                }
            });
        }

        return $query->orderByDesc('id')->paginate(10)->appends($filters)
            ->through(fn (Order $order): array => $this->serialize($order));
    }

    public function details(User $user, int $id): array
    {
        return $this->serialize($this->accessibleOrders($user)->findOrFail($id));
    }

    public function serialize(Order $order): array
    {
        return [
            'id' => $order->id,
            'description' => $order->description,
            'carrier' => $order->carrier,
            'sender' => $order->sender,
            'tracking_code' => $order->tracking_code,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'created_at' => $order->created_at?->toISOString(),
            'received_at' => $order->received_at?->toISOString(),
            'picked_up_at' => $order->picked_up_at?->toISOString(),
            'status_date' => $order->statusDate()?->toISOString(),
            'unit' => $order->unit->only(['id', 'block', 'number']),
            'resident_name' => $order->resident?->name,
            'received_by' => $order->receivedBy?->name,
            'pickup_confirmed_by' => $order->pickupConfirmedBy?->name,
            'available_for_pickup' => $order->isAvailableForPickup(),
            'can_pickup' => $order->canConfirmPickup(),
        ];
    }

    public function statusOptions(): array
    {
        return array_map(static fn (OrderStatus $status): array => ['value' => $status->value, 'label' => $status->label()], OrderStatus::cases());
    }

    public function filters(array $filters): array
    {
        return [
            'status' => $filters['status'] ?? '',
            'date_from' => $filters['date_from'] ?? '',
            'date_to' => $filters['date_to'] ?? '',
            'search' => $filters['search'] ?? '',
            'unit_id' => isset($filters['unit_id']) ? (int) $filters['unit_id'] : null,
        ];
    }
}
