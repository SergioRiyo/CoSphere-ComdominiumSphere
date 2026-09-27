<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PortariaOrderQueryService
{
    /** @param array{unit_id?: int|string|null, search?: string|null, page?: int|string|null} $filters */
    public function expectedOrders(array $filters): LengthAwarePaginator
    {
        $query = Order::query()->with(['unit:id,block,number', 'resident:id,name,unit_id,role,is_active'])
            ->where('status', OrderStatus::WaitingDelivery);

        if (! empty($filters['unit_id'])) {
            $query->where('unit_id', $filters['unit_id']);
        }
        if (isset($filters['search']) && trim($filters['search']) !== '') {
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['search'])).'%';
            $query->where(function (Builder $query) use ($search): void {
                $query->whereRaw("LOWER(tracking_code) LIKE LOWER(?) ESCAPE '!'", [$search])
                    ->orWhereRaw("LOWER(sender) LIKE LOWER(?) ESCAPE '!'", [$search]);
            });
        }

        return $query->orderByDesc('id')->paginate(10)->withQueryString()
            ->through(static fn (Order $order): array => [
                'id' => $order->id,
                'description' => $order->description,
                'carrier' => $order->carrier,
                'sender' => $order->sender,
                'tracking_code' => $order->tracking_code,
                'unit' => $order->unit->only(['id', 'block', 'number']),
                'resident_name' => $order->resident?->name,
                'can_receive' => $order->resident !== null
                    && $order->resident->is_active
                    && $order->resident->role === UserRole::Morador
                    && $order->resident->unit_id === $order->unit_id,
            ]);
    }

    public function unitOptions(): Collection
    {
        return Unit::query()->orderBy('block')->orderBy('number')->get(['id', 'block', 'number']);
    }

    public function residentOptions(?int $unitId): Collection
    {
        if ($unitId === null) {
            return collect();
        }

        return User::query()->where('unit_id', $unitId)->where('role', UserRole::Morador)
            ->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }
}
