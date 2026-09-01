<?php

namespace App\Http\Requests;

use App\Enums\VisitorAuthorizationStatus;
use App\Models\VisitorAuthorization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexVisitorAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', VisitorAuthorization::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $dateToRules = ['nullable', 'date'];

        if ($this->filled('date_from')) {
            $dateToRules[] = 'after_or_equal:date_from';
        }

        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(VisitorAuthorizationStatus::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => $dateToRules,
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
