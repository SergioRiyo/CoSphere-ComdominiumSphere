<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReservationCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, [UserRole::Admin, UserRole::Morador], true);
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'area' => ['nullable', 'integer', 'exists:common_areas,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->hasAny(['from', 'to']) && Carbon::parse($this->input('from'))->diffInDays(Carbon::parse($this->input('to'))) > 62) {
                $validator->errors()->add('to', 'O período deve ter no máximo 63 dias.');
            }
        }];
    }
}
