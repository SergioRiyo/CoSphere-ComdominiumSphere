<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteVisitorInvitationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $cpf = (string) preg_replace('/\D/', '', (string) $this->input('cpf'));
        $phone = (string) preg_replace('/\D/', '', (string) $this->input('phone'));
        $plate = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $this->input('vehicle_plate')));

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'cpf' => strlen($cpf) === 11 ? vsprintf('%s%s%s.%s%s%s.%s%s%s-%s%s', str_split($cpf)) : $cpf,
            'phone' => match (strlen($phone)) {
                10 => vsprintf('(%s%s) %s%s%s%s-%s%s%s%s', str_split($phone)),
                11 => vsprintf('(%s%s) %s%s%s%s%s-%s%s%s%s', str_split($phone)),
                default => $phone,
            },
            'vehicle_plate' => strlen($plate) === 7 ? substr($plate, 0, 3).'-'.substr($plate, 3) : $plate,
        ]);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'cpf' => ['required', 'regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$/'], 'phone' => ['required', 'regex:/^\(\d{2}\) \d{4,5}-\d{4}$/'], 'vehicle_plate' => ['nullable', 'regex:/^[A-Z]{3}-\d[A-Z0-9]\d{2}$/'], 'confirmed' => ['accepted']];
    }
}
