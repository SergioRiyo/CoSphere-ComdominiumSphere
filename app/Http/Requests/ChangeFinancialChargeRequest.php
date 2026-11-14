<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeFinancialChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin && $this->user()->is_active && $this->user()->hasVerifiedEmail();
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['pay', 'reopen', 'cancel'])],
            'paid_at' => ['nullable', 'required_if:action,pay', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reason' => ['nullable', 'required_if:action,cancel', 'string', 'max:255'],
        ];
    }
}
