<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\VerificationEmailSender;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ResponseException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

final class AuthController extends Controller
{
    private const int LOGIN_ATTEMPTS = 5;

    public function showLogin(Request $request): View
    {
        $email = $request->session()->get('loginEmail');
        $key = 'login:'.Str::lower($email ?? '').'|'.$request->ip();

        return view('auth.login', [
            'loginEmail' => $email,
            'loginCooldown' => $email !== null && RateLimiter::tooManyAttempts($key, self::LOGIN_ATTEMPTS)
                ? RateLimiter::availableIn($key)
                : 0,
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $key = 'login:'.Str::lower($credentials['email']).'|'.$request->ip();
        $request->session()->put('loginEmail', $credentials['email']);

        if (! RateLimiter::tooManyAttempts($key, self::LOGIN_ATTEMPTS)) {
            if (Auth::attempt($credentials, $request->boolean('remember'))) {
                RateLimiter::clear($key);
                $request->session()->forget('loginEmail');
                $request->session()->regenerate();

                return redirect()->intended();
            }

            RateLimiter::hit($key);
        }

        if (RateLimiter::tooManyAttempts($key, self::LOGIN_ATTEMPTS)) {
            return back()
                ->danger('Too many login attempts. Try again later.')
                ->with('loginCooldown', RateLimiter::availableIn($key))
                ->onlyInput('email', 'remember');
        }

        $attemptsLeft = self::LOGIN_ATTEMPTS - RateLimiter::attempts($key);

        return back()
            ->withErrors([
                'email' => "Invalid email or password. Attempts left: {$attemptsLeft}",
            ])
            ->onlyInput('email', 'remember');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8),
            ],
        ]);

        $user = new User;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = $data['password'];
        $user->role = UserRole::User;
        $user->save();

        event(new Registered($user));

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
                'verification' => 'Please wait before requesting another verification email.',
            ]);
        }

        return back()->success('A new verification link has been sent.');
    }

    public function verifyEmail(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect('/');
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function forgotPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::ResetLinkSent
            ? back()
                ->success('A password reset link has been sent.')
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

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8),
            ],
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password) {
                DB::transaction(function () use ($user, $password) {
                    $user->password = $password;
                    $user->setRememberToken(Str::random(60));
                    $user->save();

                    if (config('session.driver') === 'database') {
                        DB::connection(config('session.connection'))
                            ->table(config('session.table'))
                            ->where('user_id', $user->id)
                            ->delete();
                    }
                });

                event(new PasswordReset($user));
            }
        );

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

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        if ($request->query->has('error')) {
            $request->session()->forget('state');

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => $request->query('error') === 'access_denied'
                        ? 'Google sign-in was cancelled.'
                        : 'Google sign-in could not be completed. Please try again.',
                ]);
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Your Google sign-in session is invalid or has expired. Please try again.',
                ]);
        } catch (GuzzleException $exception) {
            Log::warning('Google sign-in request failed.', [
                'exception_type' => $exception::class,
                'status' => $exception instanceof ResponseException
                    ? $exception->getResponse()?->getStatusCode()
                    : null,
            ]);

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Google sign-in is temporarily unavailable. Please try again later.',
                ]);
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
                return redirect()
                    ->route('login')
                    ->withErrors([
                        'email' => 'This account is linked to another Google account. Use your original sign-in method.',
                    ]);
            }

            if ($user && ! $user->email_verified_at) {
                return redirect()
                    ->route('login')
                    ->withErrors([
                        'email' => 'An unverified account with this email already exists. Recover access to that account first.',
                    ]);
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
            $user->save();

            event(new Registered($user));
        } elseif (! $user->external_id) {
            if (! $isGoogleAuthoritative) {
                return redirect()
                    ->route('login')
                    ->withErrors([
                        'email' => 'An account with this email already exists. Sign in with your password.',
                    ]);
            }

            $user->external_id = $googleUser->getId();
            $user->save();
        }

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended('/');
    }
}
