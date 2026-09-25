<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\CommonArea;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CommonAreaAvailabilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Morador;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['date' => ['required', 'date_format:Y-m-d']];
    }

    protected function passedValidation(): void
    {
        $area = $this->route('commonArea');
        abort_unless($area instanceof CommonArea && $area->status === 'active', 404);
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Selecione uma data.',
            'date.date_format' => 'Informe uma data válida no formato AAAA-MM-DD.',
        ];
    }
}
