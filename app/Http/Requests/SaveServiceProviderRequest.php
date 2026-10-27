<?php

namespace App\Http\Requests;

use App\Models\ServiceProvider;
use Illuminate\Foundation\Http\FormRequest;

class SaveServiceProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $provider = $this->route('service_provider');

        return $provider ? $this->user()->can('update', $provider) : $this->user()->can('create', ServiceProvider::class);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string'], 'cpf_cnpj' => ['nullable', 'string'], 'phone' => ['nullable', 'string'],
            'email' => ['nullable', 'string'], 'specialty' => ['nullable', 'string']];
    }
}
