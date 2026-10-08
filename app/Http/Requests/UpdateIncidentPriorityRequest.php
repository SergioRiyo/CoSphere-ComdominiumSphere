<?php

namespace App\Http\Requests;

use App\Enums\IncidentPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncidentPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updatePriority', $this->route('incident')) ?? false;
    }

    public function rules(): array
    {
        return ['priority' => ['required', Rule::enum(IncidentPriority::class)]];
    }

    public function messages(): array
    {
        return ['priority.required' => 'Selecione a prioridade.', 'priority.enum' => 'Selecione uma prioridade válida.'];
    }
}
