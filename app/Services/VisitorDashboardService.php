<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\VisitorAccessStatus;
use App\Enums\VisitorAuthorizationStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;

class VisitorDashboardService
{
    public function activeAuthorizationsForResident(User $resident): int
    {
        if ($resident->unit_id === null) {
            return 0;
        }

        return VisitorAuthorization::query()
            ->where('unit_id', $resident->unit_id)
            ->where('status', VisitorAuthorizationStatus::Active)
            ->where('end_date', '>=', now())
            ->count();
    }

    public function presentVisitors(): int
    {
        return VisitorAccess::query()
            ->where('validation_status', VisitorAccessStatus::Validated)
            ->whereNotNull('entry_time')
            ->whereNull('exit_time')
            ->count();
    }

    public function portariaOverview(): array
    {
        $orders = Order::query()->with('unit:id,block,number')
            ->latest('updated_at')->latest('id')->limit(5)
            ->get(['id', 'unit_id', 'description', 'status', 'created_at', 'updated_at', 'received_at', 'picked_up_at']);

        $accesses = VisitorAccess::query()->with([
            'visitorAuthorization:id,visitor_id,unit_id',
            'visitorAuthorization.visitor:id,name',
            'visitorAuthorization.unit:id,block,number',
        ])->latest('updated_at')->latest('id')->limit(5)
            ->get(['id', 'visitor_authorization_id', 'validation_status', 'entry_time', 'exit_time', 'created_at', 'updated_at']);

        return [
            'waiting_orders' => Order::query()->where('status', OrderStatus::WaitingDelivery)->count(),
            'pickup_orders' => Order::query()->where('status', OrderStatus::ReceivedAtGate)->count(),
            'recent_orders' => $orders->map(fn (Order $order): array => [
                'id' => $order->id,
                'description' => $order->description,
                'status' => $order->status->label(),
                'unit' => $order->unit?->only(['block', 'number']),
                'date' => $order->statusDate()?->toISOString(),
            ]),
            'recent_accesses' => $accesses->map(function (VisitorAccess $access): array {
                $status = match (true) {
                    $access->validation_status === VisitorAccessStatus::Rejected => 'Entrada negada',
                    $access->exit_time !== null => 'Saída registrada',
                    $access->entry_time !== null => 'Presente',
                    default => 'Aguardando validação',
                };

                return [
                    'id' => $access->id,
                    'visitor' => $access->visitorAuthorization?->visitor?->name ?? 'Visitante não identificado',
                    'unit' => $access->visitorAuthorization?->unit?->only(['block', 'number']),
                    'status' => $status,
                    'date' => ($access->exit_time ?? $access->entry_time ?? $access->created_at)?->toISOString(),
                ];
            }),
        ];
    }
}
