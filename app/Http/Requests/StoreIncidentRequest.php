<?php

namespace App\Http\Requests;

use App\Enums\IncidentCategory;
use App\Enums\IncidentType;
use App\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Incident::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(IncidentType::class)],
            'category' => ['required', Rule::enum(IncidentCategory::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'attachments' => ['sometimes', 'array'],
            'attachments.*' => ['required', 'file'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Selecione o tipo da solicitação.',
            'type.enum' => 'Selecione um tipo válido.',
            'category.required' => 'Selecione uma categoria.',
            'category.enum' => 'Selecione uma categoria válida.',
            'title.required' => 'Informe o título.',
            'title.max' => 'O título deve ter no máximo 255 caracteres.',
            'description.required' => 'Descreva a solicitação.',
            'description.max' => 'A descrição deve ter no máximo 10.000 caracteres.',
            'attachments.array' => 'Selecione arquivos válidos.',
            'attachments.*.file' => 'Não foi possível enviar o arquivo. Tente novamente.',
            'attachments.*.uploaded' => 'Não foi possível enviar o arquivo. Verifique o tamanho e tente novamente.',
        ];
    }
}
