<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnexpectedOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Porteiro;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'resident_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Morador->value)->where('is_active', true)],
            'description' => ['required', 'string', 'max:5000'],
            'carrier' => ['nullable', 'string', 'max:255'],
            'sender' => ['nullable', 'string', 'max:255'],
            'tracking_code' => ['nullable', 'string', 'max:255'],
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
            'unit_id.required' => 'Selecione a unidade destinatária.',
            'unit_id.exists' => 'A unidade selecionada não existe.',
            'resident_id.required' => 'Selecione o morador destinatário.',
            'resident_id.exists' => 'Selecione um morador ativo válido.',
            '*.missing' => 'O campo :attribute é controlado pelo sistema e não pode ser enviado.',
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
