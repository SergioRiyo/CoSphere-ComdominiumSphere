<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReceiveOrderRequest extends FormRequest
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
            'unit_id' => ['missing'],
            'resident_id' => ['missing'],
            'status' => ['missing'],
            'received_by_id' => ['missing'],
            'picked_up_by_id' => ['missing'],
            'received_at' => ['missing'],
            'picked_up_at' => ['missing'],
            'expected_delivery_date' => ['missing'],
            'description' => ['missing'],
            'carrier' => ['missing'],
            'sender' => ['missing'],
            'tracking_code' => ['missing'],
        ];
    }

    public function messages(): array
    {
        return ['*.missing' => 'O campo :attribute não pode ser alterado no recebimento da previsão.'];
    }
}
