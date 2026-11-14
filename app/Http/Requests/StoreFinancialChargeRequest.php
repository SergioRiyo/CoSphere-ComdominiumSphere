<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin && $this->user()->is_active && $this->user()->hasVerifiedEmail();
    }

    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'integer', Rule::exists(Unit::class, 'id')],
            'description' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/', 'numeric', 'gt:0'],
            'due_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
