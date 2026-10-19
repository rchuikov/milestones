<?php

namespace App\Actions\User;

use App\Enums\Locale;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

final class RegisterUser
{
    public function handle(array $data): User
    {
        $user = new User;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = $data['password'];
        $user->role = UserRole::User;
        $user->locale = Locale::from($data['locale']);
        $user->save();

        event(new Registered($user));

        return $user;
    }
}
