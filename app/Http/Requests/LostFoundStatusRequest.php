<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LostFoundStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()->hasVerifiedEmail()
            && in_array($this->user()->role, [UserRole::Admin, UserRole::Morador], true);
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['open', 'in_progress', 'resolved'])]];
    }
}
