@extends('layouts.empty')

@section('title', __('Verify email'))
@section('heading', __('Verify your email'))

@section('content')
    <p class="text-gray-600">
        {{ __('We sent a verification link to') }}
        <strong>{{ auth()->user()->email }}</strong>.
    </p>

    <p class="mt-2 text-sm text-gray-500">
        {{ __('Please check your inbox and follow the link to activate your account.') }}
    </p>

    @error('verification')
    <p class="mt-4 text-sm text-amber-800">{{ $message }}</p>
    @enderror

    <div class="mt-6 flex items-center justify-between gap-4">
        <form method="POST" action="{{ route('verification.resend') }}">
            @csrf

            <x-cooldown-button
                :label="__('Resend verification email')"
                :countdown-label="__('Resend email in')"
                :remaining-seconds="$verificationCooldown"
            />
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button
                type="submit"
                class="inline-flex cursor-pointer items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
            >
                {{ __('Logout') }}
            </button>
        </form>
    </div>
@endsection
