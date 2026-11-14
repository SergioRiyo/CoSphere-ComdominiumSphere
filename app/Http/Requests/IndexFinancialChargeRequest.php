<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexFinancialChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, [UserRole::Admin, UserRole::Morador], true)
            && $this->user()->is_active && $this->user()->hasVerifiedEmail();
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['open', 'overdue', 'paid', 'cancelled'])],
            'unit_id' => ['nullable', 'integer', Rule::exists(Unit::class, 'id')],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('date_from'), 'after_or_equal:date_from')],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
