<?php

namespace App\Http\Requests;

use App\Models\VisitorAuthorization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVisitorAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', VisitorAuthorization::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'cpf' => $this->normalizeCpf((string) $this->input('cpf')),
            'phone' => $this->normalizePhone((string) $this->input('phone')),
            'vehicle_plate' => $this->normalizePlate($this->input('vehicle_plate')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'string', 'regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$/'],
            'phone' => ['required', 'string', 'regex:/^\(\d{2}\) \d{4,5}-\d{4}$/'],
            'vehicle_plate' => ['nullable', 'string', 'regex:/^[A-Z]{3}-\d[A-Z0-9]\d{2}$/'],
            'start_date' => ['required', 'date', 'after:now'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'cpf.regex' => 'O CPF deve ter 11 dígitos.',
            'phone.regex' => 'O telefone deve ter 10 ou 11 dígitos.',
            'vehicle_plate.regex' => 'A placa deve estar em um formato brasileiro válido.',
            'start_date.after' => 'O início da visita não pode estar no passado.',
            'end_date.after' => 'O término deve ser posterior ao início da visita.',
        ];
    }

    private function normalizeCpf(string $cpf): string
    {
        $digits = (string) preg_replace('/\D/', '', $cpf);

        if (strlen($digits) !== 11) {
            return $cpf;
        }

        return sprintf(
            '%s.%s.%s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 3),
            substr($digits, 9, 2),
        );
    }

    private function normalizePhone(string $phone): string
    {
        $digits = (string) preg_replace('/\D/', '', $phone);

        if (strlen($digits) === 10) {
            return sprintf(
                '(%s) %s-%s',
                substr($digits, 0, 2),
                substr($digits, 2, 4),
                substr($digits, 6, 4),
            );
        }

        if (strlen($digits) === 11) {
            return sprintf(
                '(%s) %s-%s',
                substr($digits, 0, 2),
                substr($digits, 2, 5),
                substr($digits, 7, 4),
            );
        }

        return $phone;
    }

    private function normalizePlate(mixed $plate): ?string
    {
        $plate = preg_replace('/[^A-Za-z0-9]/', '', (string) $plate);

        if ($plate === '') {
            return null;
        }

        $plate = strtoupper($plate);

        return strlen($plate) === 7
            ? substr($plate, 0, 3).'-'.substr($plate, 3)
            : $plate;
    }
}
