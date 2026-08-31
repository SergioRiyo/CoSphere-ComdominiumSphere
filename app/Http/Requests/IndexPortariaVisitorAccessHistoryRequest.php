<?php

namespace App\Http\Requests;

use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPortariaVisitorAccessHistoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
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
            'unit_id' => ['nullable', 'integer', Rule::exists(Unit::class, 'id')],
            'situation' => ['nullable', Rule::in([
                'present',
                'finished',
                'denied',
                'pending',
                'validated',
            ])],
            'date_from' => ['nullable', 'date'],
            'date_to' => $dateToRules,
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
