<?php

namespace App\Services;

use App\Enums\Locale;
use App\Enums\UserRole;
use App\Models\User;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ResponseException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

final class GoogleAuthenticator
{
    public function authenticate(?Locale $locale): User
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            throw ValidationException::withMessages(['email' => __('Your Google sign-in session is invalid or has expired. Please try again.')]);
        } catch (GuzzleException $exception) {
            Log::warning('Google sign-in request failed.', [
                'exception_type' => $exception::class,
                'status' => $exception instanceof ResponseException
                    ? $exception->getResponse()?->getStatusCode()
                    : null,
            ]);

            throw ValidationException::withMessages(['email' => __('Google sign-in is temporarily unavailable. Please try again later.')]);
        }

        $googleData = $googleUser->user;
        $email = $googleUser->getEmail();

        $isGoogleAuthoritative = str_ends_with(strtolower($email), '@gmail.com')
            || (
                ($googleData['email_verified'] ?? false)
                && ! empty($googleData['hd'])
            );

        $user = User::query()
            ->where('external_id', $googleUser->getId())
            ->first();

        if (! $user) {
            $user = User::query()
                ->where('email', $email)
                ->first();

            if ($user && $user->external_id !== null) {
                throw ValidationException::withMessages(['email' => __('This account is linked to another Google account. Use your original sign-in method.')]);
            }

            if ($user && ! $user->email_verified_at) {
                throw ValidationException::withMessages(['email' => __('An unverified account with this email already exists. Recover access to that account first.')]);
            }
        }

        if (! $user) {
            $user = new User;
            $user->name = $googleUser->getName() ?: $email;
            $user->email = $email;
            $user->external_id = $googleUser->getId();
            $user->email_verified_at = $isGoogleAuthoritative
                ? now()
                : null;
            $user->role = UserRole::User;
            $user->locale = $locale ?? Locale::default();
            $user->save();

            event(new Registered($user));
        } else {
            if (! $user->external_id) {
                if (! $isGoogleAuthoritative) {
                    throw ValidationException::withMessages([
                        'email' => __('An account with this email already exists. Sign in with your password.'),
                    ]);
                }

                $user->external_id = $googleUser->getId();
            }

            if ($locale !== null) {
                $user->locale = $locale;
            }

            if ($user->isDirty()) {
                $user->save();
            }
        }

        return $user;
    }
}
