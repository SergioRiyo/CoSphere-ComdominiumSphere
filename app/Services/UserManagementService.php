<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class UserManagementService
{
    /**
     * @param  array{search?: string, role?: string, status?: string}  $filters
     */
    public function paginate(User $actor, array $filters): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;
        $activeAdministratorCount = User::query()
            ->where('role', UserRole::Admin)
            ->where('is_active', true)
            ->count();

        return User::query()
            ->select(['id', 'unit_id', 'name', 'email', 'cpf', 'phone', 'role', 'is_active'])
            ->with('unit:id,block,number')
            ->when($search, function (Builder $query, string $search): void {
                $searchPattern = '%'.$search.'%';

                $query->where(function (Builder $query) use ($searchPattern): void {
                    $query->whereLike('name', $searchPattern)
                        ->orWhereLike('cpf', $searchPattern)
                        ->orWhereLike('email', $searchPattern);
                });
            })
            ->when(
                $filters['role'] ?? null,
                fn (Builder $query, string $role): Builder => $query->where('role', $role),
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status): Builder => $query->where(
                    'is_active',
                    $status === 'active',
                ),
            )
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString()
            ->through(static fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'cpf' => $user->cpf,
                'phone' => $user->phone,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'is_active' => $user->is_active,
                'can_change_role' => $user->id !== $actor->id
                    && ! ($user->role === UserRole::Admin && $user->is_active && $activeAdministratorCount === 1),
                'can_change_status' => ! $user->is_active || ($user->id !== $actor->id
                    && ! ($user->role === UserRole::Admin && $activeAdministratorCount === 1)),
                'unit' => $user->unit === null ? null : [
                    'id' => $user->unit->id,
                    'block' => $user->unit->block,
                    'number' => $user->unit->number,
                ],
            ]);
    }

    /**
     * @return Collection<int, array{id: int, block: string|null, number: string}>
     */
    public function unitOptions(): Collection
    {
        return Unit::query()
            ->select(['id', 'block', 'number'])
            ->orderBy('block')
            ->orderBy('number')
            ->get()
            ->map(static fn (Unit $unit): array => [
                'id' => $unit->id,
                'block' => $unit->block,
                'number' => $unit->number,
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        $role = UserRole::from($data['role']);

        return User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'cpf' => $data['cpf'],
            'phone' => $data['phone'],
            'role' => $role,
            'unit_id' => $this->unitIdFor($role, $data['unit_id'] ?? null),
            'password' => $data['password'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, User $user, array $data): User
    {
        return DB::transaction(function () use ($actor, $user, $data): User {
            $activeAdministratorIds = $this->lockActiveAdministrators();
            $current = User::query()->lockForUpdate()->findOrFail($user->id);
            $role = UserRole::from($data['role']);

            if ($current->role === UserRole::Admin && $current->is_active && $role !== UserRole::Admin) {
                if ($activeAdministratorIds->count() === 1) {
                    throw ValidationException::withMessages([
                        'role' => 'Não é possível alterar o perfil do último administrador ativo.',
                    ]);
                }

                if ($current->id === $actor->id) {
                    throw ValidationException::withMessages([
                        'role' => 'Você não pode alterar o próprio perfil administrativo.',
                    ]);
                }
            }

            $current->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'cpf' => $data['cpf'],
                'phone' => $data['phone'],
                'role' => $role,
                'unit_id' => $this->unitIdFor($role, $data['unit_id'] ?? null),
            ]);

            return $current->refresh();
        }, 3);
    }

    public function updateStatus(User $actor, User $user, bool $isActive): User
    {
        return DB::transaction(function () use ($actor, $user, $isActive): User {
            $activeAdministratorIds = $this->lockActiveAdministrators();
            $current = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $isActive && $current->is_active) {
                if ($current->role === UserRole::Admin && $activeAdministratorIds->count() === 1) {
                    throw ValidationException::withMessages([
                        'is_active' => 'Não é possível inativar o último administrador ativo.',
                    ]);
                }

                if ($current->id === $actor->id) {
                    throw ValidationException::withMessages([
                        'is_active' => 'Você não pode inativar a própria conta.',
                    ]);
                }
            }

            $current->update(['is_active' => $isActive]);

            return $current->refresh();
        }, 3);
    }

    /** @return Collection<int, int> */
    private function lockActiveAdministrators(): Collection
    {
        return User::query()
            ->where('role', UserRole::Admin)
            ->where('is_active', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');
    }

    private function unitIdFor(UserRole $role, ?int $unitId): ?int
    {
        if ($role !== UserRole::Morador) {
            return null;
        }

        if ($unitId === null) {
            throw new LogicException('Moradores devem estar vinculados a uma unidade.');
        }

        return $unitId;
    }
}
