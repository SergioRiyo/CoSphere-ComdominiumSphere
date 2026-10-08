<?php

namespace App\Http\Requests;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\Unit;
use App\Models\User;
use App\Services\IncidentQueryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Incident::class) ?? false;
    }

    public function rules(IncidentQueryService $query): array
    {
        $admin = $this->user()->role === UserRole::Admin;

        return [
            'type' => ['nullable', Rule::enum(IncidentType::class)],
            'category' => ['nullable', 'string', Rule::in(array_column($query->categories($this->user()), 'value'))],
            'status' => ['nullable', Rule::enum(IncidentStatus::class)],
            'priority' => $admin ? ['nullable', Rule::enum(IncidentPriority::class)] : ['prohibited'],
            'resident_id' => $admin ? ['nullable', 'integer', Rule::exists(User::class, 'id')] : ['prohibited'],
            'unit_id' => $admin ? ['nullable', 'integer', Rule::exists(Unit::class, 'id')] : ['prohibited'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('date_from'), 'after_or_equal:date_from')],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.enum' => 'Selecione uma opção válida.',
            'category.in' => 'Selecione uma categoria válida.',
            '*.exists' => 'Selecione um registro existente.',
            '*.integer' => 'Informe um identificador válido.',
            '*.date_format' => 'Informe uma data válida.',
            'date_to.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            '*.prohibited' => 'Este filtro é exclusivo da administração.',
            'page.min' => 'Informe uma página válida.',
        ];
    }
}
