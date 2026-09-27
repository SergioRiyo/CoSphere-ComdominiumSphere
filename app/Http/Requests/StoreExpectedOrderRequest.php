<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpectedOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Morador;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:5000'],
            'carrier' => ['nullable', 'string', 'max:255'],
            'sender' => ['nullable', 'string', 'max:255'],
            'tracking_code' => ['nullable', 'string', 'max:255'],
            'unit_id' => ['missing'],
            'resident_id' => ['missing'],
            'status' => ['missing'],
            'received_by_id' => ['missing'],
            'picked_up_by_id' => ['missing'],
            'received_at' => ['missing'],
            'picked_up_at' => ['missing'],
            'expected_delivery_date' => ['missing'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'Informe a identificação/descrição da encomenda.',
            '*.string' => 'O campo :attribute deve ser um texto.',
            '*.max' => 'O campo :attribute deve ter no máximo :max caracteres.',
            '*.missing' => 'O campo :attribute não pode ser enviado no cadastro de encomenda prevista.',
        ];
    }

    public function attributes(): array
    {
        return [
            'description' => 'identificação/descrição',
            'carrier' => 'transportadora',
            'sender' => 'remetente',
            'tracking_code' => 'código de rastreio',
        ];
    }
}
