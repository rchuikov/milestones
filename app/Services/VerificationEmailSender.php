<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

final class VerificationEmailSender
{
    public function send(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $key = $this->key($user);

        return Cache::lock($key.':lock', 60)->get(function () use ($user, $key): bool {
            if (RateLimiter::tooManyAttempts($key, 1)) {
                return false;
            }

            $user->sendEmailVerificationNotification();
            RateLimiter::hit($key, 60);

            return true;
        });
    }

    public function remainingSeconds(User $user): int
    {
        return RateLimiter::availableIn($this->key($user));
    }

    private function key(User $user): string
    {
        return 'verification-email:'.$user->id.':'.sha1(strtolower($user->email));
    }
}
