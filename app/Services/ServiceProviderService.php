<?php

namespace App\Services;

use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ServiceProviderService
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = ServiceProvider::query();
        if (($filters['state'] ?? 'active') === 'archived') {
            $query->onlyTrashed();
        }
        if (($filters['state'] ?? '') === 'all') {
            $query->withTrashed();
        }
        if (! empty($filters['search'])) {
            $query->whereLike('name', '%'.$filters['search'].'%');
        }

        return $query->orderBy('name')->orderBy('id')->paginate(15, ['id', 'name', 'phone', 'email', 'specialty', 'cpf_cnpj', 'deleted_at'])->appends($filters);
    }

    public static function normalizeDocument(?string $value): ?string
    {
        $value = $value === null ? null : preg_replace('/[.\\/\\-\\s]/', '', $value);

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $data */
    public function save(User $actor, array $data, ?ServiceProvider $provider = null): ServiceProvider
    {
        try {
            return DB::transaction(function () use ($actor, $data, $provider): ServiceProvider {
                $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
                $current = $provider ? ServiceProvider::withTrashed()->lockForUpdate()->findOrFail($provider->id) : new ServiceProvider;
                Gate::forUser($actor)->authorize($provider ? 'update' : 'create', $provider ? $current : ServiceProvider::class);
                if (isset($data['cpf_cnpj']) && is_string($data['cpf_cnpj'])) {
                    $data['cpf_cnpj'] = self::normalizeDocument($data['cpf_cnpj']);
                }
                $validated = Validator::make($data, [
                    'name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:255'],
                    'email' => ['nullable', 'email', 'max:255'], 'specialty' => ['nullable', 'string', 'max:255'],
                    'cpf_cnpj' => ['nullable', 'string', 'regex:/^(?:[0-9]{11}|[0-9]{14})$/D', Rule::unique('service_providers')->ignore($current->id)],
                ], ['cpf_cnpj.unique' => 'Este CPF/CNPJ já pertence a um prestador, inclusive arquivado.',
                    'cpf_cnpj.regex' => 'Informe um CPF com 11 dígitos ou CNPJ com 14 dígitos.'])->validate();
                if (! $current->fill($validated)->save()) {
                    throw new RuntimeException('Não foi possível salvar o prestador.');
                }

                return $current;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['cpf_cnpj' => 'Este CPF/CNPJ já pertence a um prestador.']);
        }
    }

    public function archive(User $actor, ServiceProvider $provider): void
    {
        DB::transaction(function () use ($actor, $provider): void {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $current = ServiceProvider::withTrashed()->lockForUpdate()->findOrFail($provider->id);
            Gate::forUser($actor)->authorize('archive', $current);
            if (! $current->delete()) {
                throw new RuntimeException('Não foi possível arquivar o prestador.');
            }
        });
    }
}
