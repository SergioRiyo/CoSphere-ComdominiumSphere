<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\CommonArea;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommonAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User
            && $this->user()->role === UserRole::Admin;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var CommonArea $commonArea */
        $commonArea = $this->route('common_area');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(CommonArea::class)->ignore($commonArea)],
            'description' => ['nullable', 'string'],
            'available_from' => ['nullable', 'required_with:available_until', 'date_format:H:i'],
            'available_until' => ['nullable', 'required_with:available_from', 'date_format:H:i', 'after:available_from'],
            'max_reservation_minutes' => ['required', 'integer', 'min:1', 'max:32767'],
            'rules' => ['required', 'string'],
            'requires_approval' => ['required', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive', 'maintenance'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da área.',
            'name.max' => 'O nome deve ter no máximo 255 caracteres.',
            'name.unique' => 'Já existe uma área com esse nome.',
            'description.string' => 'A descrição deve ser um texto.',
            'available_from.required_with' => 'Informe o horário de abertura.',
            'available_until.required_with' => 'Informe o horário de fechamento.',
            'available_from.date_format' => 'Informe a abertura no formato HH:mm.',
            'available_until.date_format' => 'Informe o fechamento no formato HH:mm.',
            'available_until.after' => 'O fechamento deve ser posterior à abertura, no mesmo dia.',
            'max_reservation_minutes.required' => 'Informe a duração máxima da reserva.',
            'max_reservation_minutes.integer' => 'A duração deve ser um número inteiro de minutos.',
            'max_reservation_minutes.min' => 'A duração deve ser maior que zero.',
            'max_reservation_minutes.max' => 'A duração deve ser de no máximo 32767 minutos.',
            'rules.required' => 'Informe as regras de utilização da área.',
            'rules.string' => 'As regras devem ser um texto.',
            'requires_approval.required' => 'Selecione o tipo de aprovação.',
            'requires_approval.boolean' => 'Selecione um tipo de aprovação válido.',
            'status.required' => 'Selecione o status da área.',
            'status.in' => 'Selecione um status válido.',
        ];
    }
}
