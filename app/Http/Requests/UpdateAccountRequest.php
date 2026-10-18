<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UpdateAccountRequest extends FormRequest
{
    public function rules(): array
    {
        $user = $this->user();

        if ($user->password === null) {
            return [
                'email' => ['prohibited'],
                'current_password' => ['prohibited'],
                'password' => ['required', 'confirmed', Password::min(8)],
            ];
        }

        return [
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user),
            ],
            'current_password' => ['required', 'current_password'],
            'password' => [
                'nullable',
                'required_with:password_confirmation',
                'confirmed',
                Password::min(8),
            ],
        ];
    }
}
