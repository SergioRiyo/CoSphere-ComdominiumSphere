<?php

namespace App\Http\Requests;

use App\Enums\IncidentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncidentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transition', $this->route('incident')) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(IncidentStatus::class)],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Selecione o novo status.',
            'status.enum' => 'Selecione um status válido.',
            'reason.max' => 'O motivo deve ter no máximo 255 caracteres.',
        ];
    }
}
