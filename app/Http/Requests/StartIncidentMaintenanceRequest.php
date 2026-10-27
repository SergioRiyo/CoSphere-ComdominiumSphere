<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartIncidentMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('createMaintenance', $this->route('incident')) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
