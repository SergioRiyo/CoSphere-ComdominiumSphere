<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\CommonArea;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Morador
            && $this->user()->is_active
            && $this->user()->hasVerifiedEmail();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'common_area_id' => ['bail', 'required', 'integer', Rule::exists(CommonArea::class, 'id')],
            'starts_at' => ['required', 'date_format:Y-m-d H:i,Y-m-d H:i:s'],
            'ends_at' => ['required', 'date_format:Y-m-d H:i,Y-m-d H:i:s'],
        ];
    }

    /** @return array<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('starts_at') || $validator->errors()->has('ends_at')) {
                return;
            }
            $start = Carbon::parse($this->input('starts_at'));
            $end = Carbon::parse($this->input('ends_at'));
            if (! $start->isSameDay($end)) {
                $validator->errors()->add('ends_at', 'A reserva deve iniciar e terminar no mesmo dia.');
            }
            if ($start->greaterThanOrEqualTo($end)) {
                $validator->errors()->add('ends_at', 'O horário final deve ser posterior ao inicial.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'common_area_id.required' => 'Selecione uma área.',
            'common_area_id.integer' => 'Selecione uma área válida.',
            'common_area_id.exists' => 'A área selecionada não existe mais.',
            'starts_at.required' => 'Informe o horário inicial.',
            'ends_at.required' => 'Informe o horário final.',
            'starts_at.date_format' => 'Informe uma data e horário inicial válidos.',
            'ends_at.date_format' => 'Informe uma data e horário final válidos.',
        ];
    }
}
