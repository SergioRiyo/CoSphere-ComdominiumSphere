<?php

namespace App\Http\Requests;

use App\Models\ServiceProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexServiceProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ServiceProvider::class);
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100'], 'state' => ['nullable', Rule::in(['active', 'archived', 'all'])], 'page' => ['nullable', 'integer', 'min:1']];
    }
}
