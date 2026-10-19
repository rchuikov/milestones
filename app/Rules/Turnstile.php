<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class Turnstile implements ValidationRule
{
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail,
    ): void {
        if (! is_string($value) || $value === '') {
            $fail(__('Please complete the security verification.'));

            return;
        }

        $secret = config('services.turnstile.secret_key');

        if (! is_string($secret) || $secret === '') {
            Log::error('Turnstile secret key is not configured.');

            $fail(__('Security verification is temporarily unavailable. Please try again.'));

            return;
        }

        try {
            $response = Http::timeout(5)->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                [
                    'secret' => $secret,
                    'response' => $value,
                ],
            );
        } catch (ConnectionException $exception) {
            Log::warning('Turnstile Siteverify connection failed.', [
                'exception_type' => $exception::class,
            ]);

            $fail(__('Security verification is temporarily unavailable. Please try again.'));

            return;
        }

        if ($response->serverError()) {
            Log::warning('Turnstile Siteverify server error.', [
                'status' => $response->status(),
            ]);

            $fail(__('Security verification is temporarily unavailable. Please try again.'));

            return;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            $technicalErrors = array_values(array_intersect(
                ['missing-input-secret', 'invalid-input-secret', 'internal-error'],
                (array) $response->json('error-codes', []),
            ));

            if ($technicalErrors !== []) {
                Log::warning('Turnstile Siteverify technical error.', [
                    'error_codes' => $technicalErrors,
                ]);

                $fail(__('Security verification is temporarily unavailable. Please try again.'));

                return;
            }

            $fail(__('Security verification failed. Please try again.'));
        }
    }
}
