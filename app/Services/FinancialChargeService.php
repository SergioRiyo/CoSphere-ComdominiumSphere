<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\FinancialCharge;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialChargeService
{
    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function index(User $user, array $filters): array
    {
        $admin = $user->role === UserRole::Admin;
        $query = FinancialCharge::query()->with('unit:id,block,number,complement');
        if (! $admin) {
            $query->where('unit_id', $user->unit_id ?? 0);
        } elseif (! empty($filters['unit_id'])) {
            $query->where('unit_id', $filters['unit_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('due_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('due_date', '<=', $filters['date_to']);
        }
        $summary = [
            'open_cents' => (int) (clone $query)->where('status', 'open')->sum('amount_cents'),
            'overdue_cents' => (int) (clone $query)->where('status', 'open')->whereDate('due_date', '<', today())->sum('amount_cents'),
            'overdue_count' => (clone $query)->where('status', 'open')->whereDate('due_date', '<', today())->count(),
        ];
        match ($filters['status'] ?? '') {
            'open' => $query->where('status', 'open')->whereDate('due_date', '>=', today()),
            'overdue' => $query->where('status', 'open')->whereDate('due_date', '<', today()),
            'paid', 'cancelled' => $query->where('status', $filters['status']),
            default => null,
        };
        $charges = $query->orderByDesc('due_date')->orderByDesc('id')->paginate(15)->appends($filters)
            ->through(fn (FinancialCharge $charge): array => $this->serialize($charge));

        return [
            'charges' => $charges,
            'filters' => $filters,
            'summary' => $summary,
            'units' => $admin ? Unit::query()->orderBy('block')->orderBy('number')->get(['id', 'block', 'number', 'complement'])
                ->map(fn (Unit $unit): array => ['id' => $unit->id, 'label' => $this->unitLabel($unit)]) : [],
        ];
    }

    /** @return array<string, mixed> */
    public function detail(User $user, FinancialCharge $charge): array
    {
        $this->authorizeCharge($user, $charge);
        $charge->load('unit:id,block,number,complement');
        $data = $this->serialize($charge);
        if ($user->role === UserRole::Admin) {
            $charge->load('history.actor:id,name');
            $data['history'] = $charge->history->map(fn ($event): array => [
                'action' => $event->action, 'actor' => $event->actor->name,
                'reason' => $event->reason, 'before' => $event->before, 'after' => $event->after,
                'created_at' => $event->created_at->format('d/m/Y H:i'),
            ]);
        }

        return $data;
    }

    public function authorizeCharge(User $user, FinancialCharge $charge): void
    {
        abort_unless($user->is_active && $user->hasVerifiedEmail() && (
            $user->role === UserRole::Admin ||
            ($user->role === UserRole::Morador && $user->unit_id !== null && $user->unit_id === $charge->unit_id)
        ), 403);
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): FinancialCharge
    {
        return DB::transaction(function () use ($user, $data): FinancialCharge {
            $charge = FinancialCharge::create($this->chargeValues($data));
            $this->record($charge, $user, 'created', null);

            return $charge;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, FinancialCharge $charge, array $data): void
    {
        DB::transaction(function () use ($user, $charge, $data): void {
            $current = FinancialCharge::query()->lockForUpdate()->findOrFail($charge->id);
            if ($current->status !== 'open') {
                throw ValidationException::withMessages(['charge' => 'Somente cobranças em aberto podem ser editadas.']);
            }
            $before = $this->snapshot($current);
            $current->update($this->chargeValues($data));
            $this->record($current, $user, 'updated', $before);
        });
    }

    /** @param array<string, mixed> $data */
    public function change(User $user, FinancialCharge $charge, array $data): void
    {
        DB::transaction(function () use ($user, $charge, $data): void {
            $current = FinancialCharge::query()->lockForUpdate()->findOrFail($charge->id);
            $action = $data['action'];
            $allowed = $action === 'reopen' ? 'paid' : 'open';
            if ($current->status !== $allowed) {
                throw ValidationException::withMessages(['charge' => 'Esta cobrança não permite a operação solicitada. Atualize a página.']);
            }
            $before = $this->snapshot($current);
            $current->update([
                'status' => match ($action) {
                    'pay' => 'paid', 'reopen' => 'open', 'cancel' => 'cancelled'
                },
                'paid_at' => $action === 'pay' ? $data['paid_at'] : null,
            ]);
            $this->record($current, $user, $action, $before, $data['reason'] ?? null);
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function chargeValues(array $data): array
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $data['amount'], 2), 2, '');

        return [
            'unit_id' => $data['unit_id'], 'description' => trim($data['description']),
            'amount_cents' => ((int) $whole * 100) + (int) str_pad($fraction, 2, '0'),
            'due_date' => $data['due_date'],
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(FinancialCharge $charge): array
    {
        return ['unit_id' => $charge->unit_id, 'description' => $charge->description,
            'amount_cents' => $charge->amount_cents, 'due_date' => $charge->due_date->toDateString(),
            'status' => $charge->status, 'paid_at' => $charge->paid_at?->toDateString()];
    }

    private function record(FinancialCharge $charge, User $user, string $action, ?array $before, ?string $reason = null): void
    {
        $charge->history()->create(['actor_id' => $user->id, 'action' => $action, 'before' => $before, 'after' => $this->snapshot($charge), 'reason' => $reason]);
    }

    /** @return array<string, mixed> */
    private function serialize(FinancialCharge $charge): array
    {
        return [
            'id' => $charge->id, 'unit_id' => $charge->unit_id, 'unit' => $this->unitLabel($charge->unit),
            'description' => $charge->description, 'amount_cents' => $charge->amount_cents,
            'due_date' => $charge->due_date->toDateString(), 'status' => $charge->displayStatus(),
            'paid_at' => $charge->paid_at?->toDateString(),
        ];
    }

    private function unitLabel(Unit $unit): string
    {
        return implode(' · ', array_filter([$unit->block ? 'Bloco '.$unit->block : null, 'Unidade '.$unit->number, $unit->complement]));
    }
}
