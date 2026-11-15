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

    protected function prepareForValidation(): void
    {
        $accessCode = $this->input('access_code');

        if (is_string($accessCode)) {
            $accessCode = trim($accessCode);
            if (preg_match('/\A[A-HJ-NP-Za-hj-np-z2-9]{4}(?:[- ]?[A-HJ-NP-Za-hj-np-z2-9]{4}){2}\z/', $accessCode)) {
                $accessCode = strtoupper(str_replace(['-', ' '], '', $accessCode));
            }

            $this->merge(['access_code' => $accessCode]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'access_code' => ['bail', 'required', 'string', 'regex:/\A(?:csa_[A-Za-z0-9]{32}|[A-HJ-NP-Z2-9]{12})\z/'],
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
            'access_code.regex' => 'O código de acesso informado é inválido.',
        ];
    }
}
