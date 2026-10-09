<?php

namespace App\Http\Requests;

use App\Enums\MaintenanceRequestStatus;
use App\Models\MaintenanceRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMaintenanceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', MaintenanceRequest::class);
    }

    public function rules(): array
    {
        return ['status' => ['nullable', Rule::enum(MaintenanceRequestStatus::class)],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'], 'admin_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_provider_id' => ['nullable', 'integer', 'exists:service_providers,id'],
            'link' => ['nullable', Rule::in(['linked', 'direct'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('date_from'), 'after_or_equal:date_from')],
            'page' => ['nullable', 'integer', 'min:1']];
    }
}
