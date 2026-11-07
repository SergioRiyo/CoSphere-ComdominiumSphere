<?php

namespace App\Http\Requests;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\MaintenanceRequestStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Services\IncidentQueryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin
            && $this->user()->is_active && $this->user()->hasVerifiedEmail();
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(IncidentQueryService $incidents): array
    {
        $rules = [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('date_from'), 'after_or_equal:date_from')],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['prohibited'], 'direction' => ['prohibited'], 'order_by' => ['prohibited'],
        ];

        return $rules + match ($this->report()) {
            'reservations' => [
                'common_area_id' => ['nullable', 'integer', 'exists:common_areas,id'],
                'unit_id' => ['nullable', 'integer', 'exists:units,id'],
                'resident_id' => ['nullable', 'integer', 'exists:users,id'],
                'status' => ['nullable', Rule::enum(ReservationStatus::class)],
            ],
            'incidents' => [
                'unit_id' => ['nullable', 'integer', 'exists:units,id'],
                'resident_id' => ['nullable', 'integer', 'exists:users,id'],
                'status' => ['nullable', Rule::enum(IncidentStatus::class)],
                'type' => ['nullable', Rule::enum(IncidentType::class)],
                'priority' => ['nullable', Rule::enum(IncidentPriority::class)],
                'category' => ['nullable', 'string', Rule::in(array_column($incidents->categories($this->user()), 'value'))],
            ],
            'maintenances' => [
                'status' => ['nullable', Rule::enum(MaintenanceRequestStatus::class)],
                'service_provider_id' => ['nullable', 'integer', 'exists:service_providers,id'],
                'date_reference' => ['nullable', Rule::in(['opening', 'scheduled', 'executed'])],
                'origin' => ['nullable', Rule::in(['direct', 'linked'])],
            ],
        };
    }

    public function report(): string
    {
        return $this->route('report');
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            '*.enum' => 'Selecione uma opção válida.', '*.in' => 'Selecione uma opção válida.',
            '*.exists' => 'Selecione um registro existente.', '*.integer' => 'Informe um identificador válido.',
            '*.date_format' => 'Informe uma data válida.',
            'date_to.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            '*.prohibited' => 'A ordenação deste relatório é fixa.',
        ];
    }
}
