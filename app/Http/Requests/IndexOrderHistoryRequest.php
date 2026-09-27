<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexOrderHistoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return in_array($this->user()?->role, [UserRole::Morador, UserRole::Porteiro], true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('date_from'), 'after_or_equal:date_from')],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'unit_id' => $this->user()?->role === UserRole::Porteiro
                ? ['nullable', 'integer', 'exists:units,id']
                : ['exclude'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.enum' => 'Selecione um status válido.',
            '*.date_format' => 'Informe uma data válida no formato ano-mês-dia.',
            'date_to.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
        ];
    }
}
