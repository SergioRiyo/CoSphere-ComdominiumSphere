<?php

namespace App\Http\Requests;

use App\Models\VisitorAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ValidateVisitorAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', VisitorAccess::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'access_code' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'access_code.required' => 'Informe o código de acesso.',
            'access_code.string' => 'O código de acesso deve ser um texto válido.',
            'access_code.max' => 'O código de acesso informado é inválido.',
        ];
    }
}
