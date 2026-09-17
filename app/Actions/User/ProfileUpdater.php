<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Str;

final class ProfileUpdater
{
    public function update(User $user, array $data): bool
    {
        $emailChanged = $user->email !== $data['email'];

        $user->name = $data['name'];
        $user->email = $data['email'];

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        if (! empty($data['password'])) {
            $user->password = $data['password'];
            $user->setRememberToken(Str::random(60));
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return $emailChanged;
    }
}
