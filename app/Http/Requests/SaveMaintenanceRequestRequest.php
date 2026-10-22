<?php

namespace App\Http\Requests;

use App\Enums\MaintenanceRequestStatus;
use App\Models\MaintenanceRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMaintenanceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $maintenance = $this->route('maintenance');

        return $maintenance ? $this->user()->can('update', $maintenance) : $this->user()->can('createAdministrative', MaintenanceRequest::class);
    }

    public function rules(): array
    {
        $creating = $this->route('maintenance') === null;
        $areaMaintenance = $creating ? $this->filled('common_area_id') : $this->route('maintenance')->common_area_id !== null;

        return [
            'description' => [$creating ? 'required' : 'sometimes', 'required', 'string'],
            'admin_id' => [$creating ? 'required' : 'sometimes', 'required', 'integer'],
            'unit_id' => $creating ? ['nullable', 'required_without:common_area_id', 'prohibits:common_area_id', 'integer'] : ['prohibited'],
            'common_area_id' => $creating
                ? ['nullable', 'required_without:unit_id', 'prohibits:unit_id', 'integer']
                : ($areaMaintenance ? ['sometimes', 'required', 'integer'] : ['prohibited']),
            'service_provider_id' => ['sometimes', 'nullable', 'integer'],
            'scheduled_at' => [$creating && $areaMaintenance ? 'required' : 'sometimes', 'nullable', 'date'],
            'cost' => ['sometimes', 'nullable'],
            'status' => $creating ? ['prohibited'] : ['sometimes', 'required', Rule::enum(MaintenanceRequestStatus::class)],
            'reason' => ['nullable', 'string', 'max:255'],
            'incident_id' => ['prohibited'], 'resident_id' => ['prohibited'], 'responsible_admin_id' => ['prohibited'],
            'executed_at' => ['prohibited'], 'changed_by_user_id' => ['prohibited'],
        ];
    }
}
