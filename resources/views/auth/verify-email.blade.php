@extends('layouts.empty')

@section('title', 'Verify email')

@section('content')
    <div class="rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
        <h1 class="mb-4 text-2xl font-semibold">
            Verify your email
        </h1>

        <p class="text-gray-600">
            We sent a verification link to
            <strong>{{ auth()->user()->email }}</strong>.
        </p>

        <p class="mt-2 text-sm text-gray-500">
            Please check your inbox and follow the link to activate your account.
        </p>

        @if (session('status') === 'verification-link-sent')
            <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">
                A new verification link has been sent.
            </div>
        @endif

        @error('verification')
            <p class="mt-4 text-sm text-amber-800">{{ $message }}</p>
        @enderror

        <div class="mt-6 flex items-center justify-between gap-4">
            <form method="POST" action="{{ route('verification.resend') }}">
                @csrf

                <x-cooldown-button
                    label="Resend verification email"
                    countdown-label="Resend email in"
                    :remaining-seconds="$verificationCooldown"
                />
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button
                    type="submit"
                    class="inline-flex cursor-pointer items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    Logout
                </button>
            </form>
        </div>
    </div>
@endsection
