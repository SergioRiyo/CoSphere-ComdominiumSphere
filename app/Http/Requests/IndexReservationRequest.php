<?php

namespace App\Http\Requests;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, [UserRole::Admin, UserRole::Morador], true)
            && $this->user()->is_active && $this->user()->hasVerifiedEmail();
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(ReservationStatus::class)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('date_from'), 'after_or_equal:date_from')],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.enum' => 'Selecione um status válido.',
            '*.date_format' => 'Informe uma data válida no formato ano-mês-dia.',
            'date_to.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            'page.integer' => 'Informe uma página válida.',
            'page.min' => 'Informe uma página válida.',
        ];
    }
}
