<?php

namespace App\Http\Requests;

use App\Enums\IncidentCategory;
use App\Enums\IncidentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin
            && $this->user()->is_active
            && $this->user()->hasVerifiedEmail();
    }

    public function rules(): array
    {
        return [
            'reservation_date_from' => ['nullable', 'date_format:Y-m-d'],
            'reservation_date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('reservation_date_from'), 'after_or_equal:reservation_date_from')],
            'reservation_status' => ['nullable', Rule::enum(ReservationStatus::class)],
            'common_area_id' => ['nullable', 'integer', 'exists:common_areas,id'],
            'reservations_page' => ['nullable', 'integer', 'min:1'],

            'access_date_from' => ['nullable', 'date_format:Y-m-d'],
            'access_date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('access_date_from'), 'after_or_equal:access_date_from')],
            'access_situation' => ['nullable', Rule::in(['pending', 'validated', 'present', 'finished', 'denied'])],
            'accesses_page' => ['nullable', 'integer', 'min:1'],

            'incident_date_from' => ['nullable', 'date_format:Y-m-d'],
            'incident_date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('incident_date_from'), 'after_or_equal:incident_date_from')],
            'incident_category' => ['nullable', Rule::enum(IncidentCategory::class)],
            'incident_status' => ['nullable', Rule::enum(IncidentStatus::class)],
            'incidents_page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.date_format' => 'Informe uma data válida.',
            '*.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            '*.enum' => 'Selecione uma opção válida.',
            '*.in' => 'Selecione uma opção válida.',
            '*.exists' => 'Selecione um registro existente.',
            '*_page.integer' => 'Informe uma página válida.',
            '*_page.min' => 'Informe uma página válida.',
        ];
    }
}
