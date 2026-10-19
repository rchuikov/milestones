<?php

namespace App\Actions\User;

use App\Enums\Locale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class LoginUser
{
    private const int LOGIN_ATTEMPTS = 5;

    public function handle(
        array $credentials,
        bool $remember,
        string $ip,
        ?Locale $locale = null,
    ): bool {
        $key = $this->throttleKey($credentials['email'], $ip);

        if (RateLimiter::tooManyAttempts($key, self::LOGIN_ATTEMPTS)) {
            return false;
        }

        if (Auth::attempt($credentials, $remember)) {
            RateLimiter::clear($key);

            if ($locale !== null) {
                Auth::user()->update(['locale' => $locale]);
            }

            return true;
        }

        RateLimiter::hit($key);

        return false;
    }

    public function remainingSeconds(?string $email, string $ip): int
    {
        if ($email === null) {
            return 0;
        }

        $key = $this->throttleKey($email, $ip);

        return RateLimiter::tooManyAttempts($key, self::LOGIN_ATTEMPTS)
            ? RateLimiter::availableIn($key)
            : 0;
    }

    public function attemptsLeft(string $email, string $ip): int
    {
        return self::LOGIN_ATTEMPTS - RateLimiter::attempts($this->throttleKey($email, $ip));
    }

    private function throttleKey(string $email, string $ip): string
    {
        return 'login:'.Str::lower($email).'|'.$ip;
    }
}
