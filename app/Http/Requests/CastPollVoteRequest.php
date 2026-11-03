<?php

namespace App\Http\Requests;

use App\Models\Poll;
use Illuminate\Foundation\Http\FormRequest;

class CastPollVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Poll::class);
    }

    public function rules(): array
    {
        return ['option_id' => ['required', 'integer', 'min:1']];
    }
}
