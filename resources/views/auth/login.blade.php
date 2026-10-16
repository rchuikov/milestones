@extends('layouts.empty')

@section('title', 'Login')

@section('content')
    <div class="bg-white rounded-lg shadow p-8">
        <h1 class="text-2xl font-semibold mb-6">
            Login
        </h1>

        <a
            href="{{ route('auth.google') }}"
            class="mb-6 flex w-full items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
        >
            <svg
                class="h-4 w-4"
                viewBox="0 0 18 18"
                aria-hidden="true"
            >
                <path fill="#4285F4" d="M17.64 9.205c0-.638-.057-1.252-.164-1.841H9v3.481h4.844a4.14 4.14 0 0 1-1.797 2.716v2.259h2.909c1.703-1.568 2.684-3.878 2.684-6.615z"/>
                <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.909-2.259c-.806.54-1.836.859-3.047.859-2.344 0-4.33-1.585-5.041-3.715H.951v2.332A9 9 0 0 0 9 18z"/>
                <path fill="#FBBC05" d="M3.959 10.705A5.41 5.41 0 0 1 3.676 9c0-.592.102-1.166.283-1.705V4.963H.951A9 9 0 0 0 0 9c0 1.45.347 2.821.951 4.037l3.008-2.332z"/>
                <path fill="#EA4335" d="M9 3.58c1.322 0 2.507.454 3.441 1.346l2.581-2.582C13.463.891 11.426 0 9 0A9 9 0 0 0 .951 4.963l3.008 2.332C4.67 5.165 6.656 3.58 9 3.58z"/>
            </svg>
            Continue with Google
        </a>

        <div class="mb-6 flex items-center gap-3">
            <div class="h-px flex-1 bg-gray-200"></div>
            <span class="text-xs uppercase text-gray-400">or</span>
            <div class="h-px flex-1 bg-gray-200"></div>
        </div>

        <form method="POST" action="{{ route('login.submit') }}">
            @csrf

            <x-form.input
                name="email"
                type="email"
                label="Email"
                :value="$loginEmail"
                required
                autofocus
            />

            <x-form.input
                name="password"
                type="password"
                label="Password"
                required
            />

            <x-form.checkbox
                name="remember"
                label="Remember me"
                :checked="old('remember')"
            />

            <x-cooldown-button
                storage-prefix="login"
                label="Login"
                countdown-label="Try again in"
                :remaining-seconds="$loginCooldown"
                class="w-full"
            />

        </form>
        <div class="mt-6 flex items-center justify-between text-sm">
            <a
                href="{{ route('register') }}"
                class="text-blue-600 hover:underline"
            >
                Register
            </a>

            <a
                href="{{ route('forgot-password') }}"
                class="text-blue-600 hover:underline"
            >
                Forgot password?
            </a>
        </div>
    </div>
@endsection
