<?php

namespace App\Http\Requests;

use App\Models\VisitorAuthorization;
use Illuminate\Foundation\Http\FormRequest;

class StoreVisitorInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', VisitorAuthorization::class) ?? false;
    }

    public function rules(): array
    {
        return ['start_date' => ['required', 'date', 'after:now'], 'end_date' => ['required', 'date', 'after:start_date']];
    }
}
