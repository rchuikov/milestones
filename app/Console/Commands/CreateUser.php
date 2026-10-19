<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

#[Signature('user:create')]
#[Description('Create a user')]
final class CreateUser extends Command
{
    public function handle(): int
    {
        $data = [
            'name' => $this->ask(__('Name')),
            'email' => $this->ask(__('Email')),
            'role' => $this->choice(
                __('Role'),
                [
                    UserRole::User->value,
                    UserRole::Admin->value,
                ],
                UserRole::User->value,
            ),
            'password' => $this->secret(__('Password')),
            'password_confirmation' => $this->secret(__('Confirm password')),

        ];

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => [
                'required',
                'confirmed',
                Password::min(8),
            ],
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = $data['password'];
        $user->role = UserRole::from($data['role']);
        $user->email_verified_at = now();
        $user->save();

        $this->info(__('User :email created.', ['email' => $user->email]));

        return self::SUCCESS;
    }
}
