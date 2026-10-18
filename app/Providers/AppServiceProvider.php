<?php

namespace App\Providers;

use App\Actions\User\SendVerificationEmail;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
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
        // Customize authentication emails
        VerifyEmail::toMailUsing(function ($notifiable, $url) {
            return (new MailMessage)
                ->subject('Verify your MyMilestones email address')
                ->greeting('Welcome to MyMilestones!')
                ->line('Thanks for joining MyMilestones! Please confirm your email address to activate your account.')
                ->action('Verify Email', $url)
                ->line("If you didn't create this account, you can safely ignore this email.")
                ->salutation("Best regards,  \nThe MyMilestones Team");
        });
        ResetPassword::toMailUsing(function ($notifiable, $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->subject('Reset your MyMilestones password')
                ->greeting('Reset your password')
                ->line('We received a request to reset your MyMilestones password.')
                ->action('Reset Password', $url)
                ->line('If you did not request a password reset, you can safely ignore this email.')
                ->salutation("Best regards,  \nThe MyMilestones Team");
        });

        // Rate limiting for authentication endpoints
        RateLimiter::for('login-submit', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinutes(10, 5)->by($request->ip());
        });
        RateLimiter::for('password-recovery', function (Request $request) {
            return Limit::perMinutes(10, 10)->by($request->ip());
        });

        // Flash message macros
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
