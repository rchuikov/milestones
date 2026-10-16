<?php

namespace App\Providers;

use App\Actions\User\SendVerificationEmail;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Replace the default Registered listener without adding a second sender.
        $this->app->bind(SendEmailVerificationNotification::class, SendVerificationEmail::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
