<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UserManagementService
{
    /**
     * @param  array{search?: string, role?: string, status?: string}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;

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
    public function update(User $user, array $data): User
    {
        $role = UserRole::from($data['role']);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'cpf' => $data['cpf'],
            'phone' => $data['phone'],
            'role' => $role,
            'unit_id' => $this->unitIdFor($role, $data['unit_id'] ?? null),
        ]);

        return $user->refresh();
    }

    public function updateStatus(User $user, bool $isActive): User
    {
        $user->update(['is_active' => $isActive]);

        return $user->refresh();
    }

    private function unitIdFor(UserRole $role, mixed $unitId): ?int
    {
        return $role === UserRole::Morador ? (int) $unitId : null;
    }
}
