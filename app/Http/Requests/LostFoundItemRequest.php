<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\LostFoundItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LostFoundItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $item = $this->route('lostFoundItem');
        if (! $user || ! $user->is_active || ! $user->hasVerifiedEmail()) {
            return false;
        }
        if (! $item) {
            return $user->role === UserRole::Morador;
        }

        return $item instanceof LostFoundItem && ($user->role === UserRole::Admin || ($user->role === UserRole::Morador && $item->author_id === $user->id && $item->status === 'open'));
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['lost', 'found'])],
            'description' => ['required', 'string', 'max:1000'],
            'condition' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'remove_photo' => ['sometimes', 'boolean'],
            'photo' => ['nullable', 'file', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $formats = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp']];
                    if (! in_array(strtolower($value->getClientOriginalExtension()), $formats[$value->getMimeType()] ?? [], true) || @getimagesize($value->getPathname()) === false) {
                        $fail('O conteúdo da foto não corresponde ao formato informado.');
                    }
                }],
        ];
    }
}
