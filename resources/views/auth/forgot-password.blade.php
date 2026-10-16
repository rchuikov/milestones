@extends('layouts.empty')

@section('title', 'Forgot password')

@section('content')
    <div class="rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
        <h1 class="mb-4 text-2xl font-semibold">
            Forgot password
        </h1>

        <p class="mb-6 text-sm text-gray-500">
            Enter your email address and we'll send you a password reset link.
        </p>

        <form method="POST" action="{{ route('forgot-password.submit') }}">
            @csrf

            <x-form.input
                name="email"
                type="email"
                label="Email"
                required
                autofocus
            />

            <div class="mt-6">
                <x-cooldown-button
                    storage-prefix="password_reset"
                    label="Send reset link"
                    countdown-label="Send again in"
                    :restart="session('passwordResetSent', false)"
                    class="w-full"
                />
            </div>

            <div class="mt-6 text-center text-sm">
                <a href="{{ route('login') }}" class="text-blue-600 hover:underline">
                    Back to login
                </a>
            </div>
        </form>
    </div>
@endsection
