<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteVisitorInvitationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name')), 'cpf' => preg_replace('/\D/', '', (string) $this->input('cpf')), 'phone' => preg_replace('/\D/', '', (string) $this->input('phone')), 'vehicle_plate' => strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $this->input('vehicle_plate')))]);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'cpf' => ['required', 'digits:11'], 'phone' => ['required', 'digits_between:10,11'], 'vehicle_plate' => ['nullable', 'regex:/^[A-Z]{3}\d[A-Z0-9]\d{2}$/'], 'confirmed' => ['accepted']];
    }
}
