<?php

namespace App\Providers;

use App\Actions\User\SendVerificationEmail;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SendEmailVerificationNotification::class,
            SendVerificationEmail::class,
        );
    }

    public function boot(): void
    {
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinutes(10, 5)->by($request->ip());
        });

        RedirectResponse::macro('success', function (string $message): RedirectResponse {
            return $this->with('flash', [
                'type' => 'success',
                'message' => $message,
            ]);
        });

        RedirectResponse::macro('info', function (string $message): RedirectResponse {
            return $this->with('flash', [
                'type' => 'info',
                'message' => $message,
            ]);
        });

        RedirectResponse::macro('warning', function (string $message): RedirectResponse {
            return $this->with('flash', [
                'type' => 'warning',
                'message' => $message,
            ]);
        });

        RedirectResponse::macro('danger', function (string $message): RedirectResponse {
            return $this->with('flash', [
                'type' => 'danger',
                'message' => $message,
            ]);
        });
    }
}
