<?php

namespace App\Providers;

use App\Actions\User\SendVerificationEmail;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
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
        //
    }
}
