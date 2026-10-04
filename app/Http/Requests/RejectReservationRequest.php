<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class RejectReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('rejection_reason'))) {
            $this->merge(['rejection_reason' => trim($this->input('rejection_reason'))]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['rejection_reason' => ['required', 'string', 'max:255']];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Informe o motivo da recusa.',
            'rejection_reason.string' => 'O motivo da recusa deve ser um texto.',
            'rejection_reason.max' => 'O motivo deve ter no máximo 255 caracteres.',
        ];
    }
}
