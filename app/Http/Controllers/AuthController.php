<?php

namespace App\Http\Controllers;

use App\Actions\User\LoginUser;
use App\Actions\User\RegisterUser;
use App\Actions\User\ResetUserPassword;
use App\Enums\Locale;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\GoogleAuthenticator;
use App\Services\VerificationEmailSender;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

final class AuthController extends Controller
{
    public function showLogin(Request $request, LoginUser $loginUser): View
    {
        return view('auth.login', [
            'loginEmail' => $request->session()->get('loginEmail'),
            'loginCooldown' => $loginUser->remainingSeconds($request->session()->get('loginEmail'), $request->ip()),
        ]);
    }

    public function login(LoginRequest $request, LoginUser $loginUser): RedirectResponse
    {
        $email = $request->validated('email');
        $ip = $request->ip();
        $request->session()->put('loginEmail', $email);

        if ($loginUser->handle(
            $request->safe()->only(['email', 'password']),
            $request->boolean('remember'),
            $ip,
            Locale::tryFrom($request->session()->get('locale')),
        )) {
            $request->session()->forget('loginEmail');
            $request->session()->regenerate();

            return redirect()->intended();
        }

        if ($cooldown = $loginUser->remainingSeconds($email, $ip)) {
            return back()
                ->danger(__('Too many login attempts. Try again later.'))
                ->with('loginCooldown', $cooldown)
                ->onlyInput('email', 'remember');
        }

        return back()
            ->withErrors(['email' => __('Invalid email or password. Attempts left: :attempts', ['attempts' => $loginUser->attemptsLeft($email, $ip)])])
            ->onlyInput('email', 'remember');
    }

    public function logout(Request $request): RedirectResponse
    {
        $locale = $request->user()->locale->value;

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $request->session()->put('locale', $locale);

        return redirect('/');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request, RegisterUser $registerUser): RedirectResponse
    {
        $user = $registerUser->handle([
            ...$request->validated(),
            'locale' => $request->session()->get('locale', Locale::default()->value),
        ]);

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }

    public function showVerifyEmail(Request $request, VerificationEmailSender $sender): View
    {
        return view('auth.verify-email', [
            'verificationCooldown' => $sender->remainingSeconds($request->user()),
        ]);
    }

    public function resendVerificationEmail(Request $request, VerificationEmailSender $sender): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect('/');
        }

        if (! $sender->send($request->user())) {
            return back()->withErrors([
                'verification' => __('Please wait before requesting another verification email.'),
            ]);
        }

        return back()->success(__('A new verification link has been sent.'));
    }

    public function verifyEmail(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect('/')
            ->success(__('Email verified successfully. Welcome to MyMilestones!'));
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function forgotPassword(ForgotPasswordRequest $request): RedirectResponse
    {
        $status = Password::sendResetLink(
            $request->safe()->only(['email'])
        );

        return $status === Password::ResetLinkSent
            ? back()
                ->success(__('A password reset link has been sent.'))
                ->with('passwordResetSent', true)
                ->withInput($request->only('email'))
            : back()
                ->withErrors(['email' => __($status)])
                ->onlyInput('email');
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request, ResetUserPassword $resetUserPassword): RedirectResponse
    {
        $status = $resetUserPassword->handle($request->validated());

        return $status === Password::PasswordReset
            ? redirect()->route('login')->success(__($status))
            : back()
                ->withErrors(['email' => __($status)])
                ->onlyInput('email');
    }

    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request, GoogleAuthenticator $authenticator): RedirectResponse
    {
        if ($request->query->has('error')) {
            $request->session()->forget('state');

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => $request->query('error') === 'access_denied'
                        ? __('Google sign-in was cancelled.')
                        : __('Google sign-in could not be completed. Please try again.'),
                ]);
        }

        try {
            $user = $authenticator->authenticate(
                Locale::tryFrom($request->session()->get('locale'))
            );
        } catch (ValidationException $exception) {
            return redirect()->route('login')->withErrors($exception->errors());
        }

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function updateLocale(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', Rule::enum(Locale::class)],
        ]);

        $locale = Locale::from($data['locale']);

        if ($user = $request->user()) {
            $user->locale = $locale;
            $user->save();
        } else {
            $request->session()->put('locale', $locale->value);
        }

        return back();
    }
}
