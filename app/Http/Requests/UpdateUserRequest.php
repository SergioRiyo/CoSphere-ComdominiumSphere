<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof User
            && $this->user()->role === UserRole::Admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user),
            ],
            'cpf' => [
                'required',
                'string',
                'regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$/',
                Rule::unique(User::class)->ignore($user),
            ],
            'phone' => ['required', 'string', 'max:30'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'unit_id' => [
                'nullable',
                'integer',
                Rule::requiredIf($this->roleIsResident()),
                Rule::exists(Unit::class, 'id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'cpf.regex' => 'O CPF deve estar no formato 000.000.000-00.',
            'unit_id.required' => 'A unidade é obrigatória para moradores.',
        ];
    }

    private function roleIsResident(): bool
    {
        return $this->input('role') === UserRole::Morador->value;
    }
}
