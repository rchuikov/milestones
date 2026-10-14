<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        $user = $this->user();

        if ($user->password === null) {
            return [
                'name' => ['prohibited'],
                'email' => ['prohibited'],
                'current_password' => ['prohibited'],
                'password' => ['required', 'confirmed', Password::min(8)],
            ];
        }

        $emailChanged = $this->input('email') !== $user->email;

        $passwordChanged = $this->filled('password')
            || $this->filled('password_confirmation');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user),
            ],
            'current_password' => [
                Rule::requiredIf($emailChanged || $passwordChanged),
                'nullable',
                'current_password',
            ],
            'password' => [
                'nullable',
                'required_with:password_confirmation',
                'confirmed',
                Password::min(8),
            ],
        ];
    }
}
