<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\CommonArea;
use App\Models\CommonAreaBlock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommonAreaBlockService
{
    public function __construct(private ReservationService $reservations) {}

    /** @param array<string, mixed> $data */
    public function create(User $admin, array $data): CommonAreaBlock
    {
        return DB::transaction(function () use ($admin, $data): CommonAreaBlock {
            Validator::make($data, ['common_area_id' => ['required', 'integer']])->validate();
            $area = CommonArea::query()->lockForUpdate()->find($data['common_area_id']);
            $admin = $this->resolveAdmin($admin);
            if ($area === null || $area->status !== 'active') {
                throw ValidationException::withMessages(['common_area_id' => 'Selecione uma área ativa para criar o bloqueio.']);
            }

            if (is_string($data['reason'] ?? null)) {
                $data['reason'] = trim($data['reason']);
            }
            $validated = Validator::make($data, [
                'starts_at' => ['required', 'date_format:Y-m-d H:i,Y-m-d H:i:s'],
                'ends_at' => ['required', 'date_format:Y-m-d H:i,Y-m-d H:i:s'],
                'reason' => ['required', 'string', 'max:255'],
            ])->validate();
            $start = Carbon::parse($validated['starts_at']);
            $end = Carbon::parse($validated['ends_at']);
            if (! $start->isSameDay($end) || $start->greaterThanOrEqualTo($end)) {
                throw ValidationException::withMessages(['ends_at' => 'O fim deve ser posterior ao início e pertencer ao mesmo dia.']);
            }
            if ($this->reservations->hasConflictingReservations($area, $start, $end)) {
                throw ValidationException::withMessages(['starts_at' => 'Existem reservas pendentes ou aprovadas neste período. Resolva essas reservas antes de criar o bloqueio.']);
            }
            if (CommonAreaBlock::query()->conflicting($area, $start, $end)->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'Já existe um bloqueio neste período.']);
            }

            $block = new CommonAreaBlock($validated);
            $block->commonArea()->associate($area);
            $block->admin()->associate($admin);
            $block->save();

            return $block;
        });
    }

    public function remove(User $admin, CommonAreaBlock $block): void
    {
        DB::transaction(function () use ($admin, $block): void {
            $areaId = CommonAreaBlock::query()->whereKey($block->getKey())->value('common_area_id');
            $area = CommonArea::query()->lockForUpdate()->findOrFail($areaId);
            $this->resolveAdmin($admin);
            $current = CommonAreaBlock::query()->where('common_area_id', $area->id)
                ->lockForUpdate()->findOrFail($block->getKey());
            $current->delete();
        });
    }

    private function resolveAdmin(User $admin): User
    {
        $current = $admin->exists ? User::query()->lockForUpdate()->find($admin->getKey()) : null;
        if ($current === null || $current->role !== UserRole::Admin || ! $current->is_active || ! $current->hasVerifiedEmail()) {
            throw new AuthorizationException('Somente administradores ativos e verificados podem gerenciar bloqueios.');
        }

        return $current;
    }

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function listing(): LengthAwarePaginator
    {
        return CommonAreaBlock::query()
            ->with(['commonArea:id,name', 'admin:id,name'])
            ->orderByDesc('starts_at')->orderByDesc('id')->paginate(15)
            ->through(fn (CommonAreaBlock $block): array => [
                'id' => $block->id,
                'area' => $block->commonArea->name,
                'date' => $block->starts_at->toDateString(),
                'start' => $block->starts_at->format('H:i:s'),
                'end' => $block->ends_at->format('H:i:s'),
                'reason' => $block->reason,
                'admin' => $block->admin->name,
            ]);
    }
}
