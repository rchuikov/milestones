<?php

namespace App\Actions\User;

use App\Models\User;
use App\Services\VerificationEmailSender;
use Illuminate\Support\Str;

final class ProfileUpdater
{
    public function __construct(private VerificationEmailSender $verificationEmailSender) {}

    public function update(User $user, array $data): bool
    {
        if ($user->password === null) {
            $user->password = $data['password'];
            $user->setRememberToken(Str::random(60));
            $user->save();

            return false;
        }

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
            $this->verificationEmailSender->send($user);
        }

        return $emailChanged;
    }
}
