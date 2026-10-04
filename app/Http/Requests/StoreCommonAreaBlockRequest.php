<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\CommonArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommonAreaBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin
            && $this->user()->is_active && $this->user()->hasVerifiedEmail();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    public function rules(): array
    {
        return [
            'common_area_id' => ['bail', 'required', 'integer', Rule::exists(CommonArea::class, 'id')],
            'starts_at' => ['required', 'date_format:Y-m-d H:i,Y-m-d H:i:s'],
            'ends_at' => ['required', 'date_format:Y-m-d H:i,Y-m-d H:i:s'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'common_area_id.required' => 'Selecione uma área.',
            'common_area_id.integer' => 'Selecione uma área válida.',
            'common_area_id.exists' => 'A área selecionada não existe mais.',
            'starts_at.required' => 'Informe o início do bloqueio.',
            'ends_at.required' => 'Informe o fim do bloqueio.',
            '*.date_format' => 'Informe uma data e horário válidos.',
            'reason.required' => 'Informe o motivo do bloqueio.',
            'reason.string' => 'Informe um motivo em texto.',
            'reason.max' => 'O motivo deve ter no máximo 255 caracteres.',
        ];
    }
}
