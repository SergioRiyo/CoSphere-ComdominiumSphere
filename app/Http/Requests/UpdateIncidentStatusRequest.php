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
            'reason' => [
                Rule::requiredIf(fn (): bool => IncidentStatus::tryFrom((string) $this->input('status'))
                    ?->requiresResidentFeedback() ?? false),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Selecione o novo status.',
            'status.enum' => 'Selecione um status válido.',
            'reason.required' => 'Informe o retorno ao morador para finalizar ou cancelar a ocorrência.',
            'reason.max' => 'O retorno ao morador deve ter no máximo 255 caracteres.',
        ];
    }
}
