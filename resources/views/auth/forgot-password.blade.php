@extends('layouts.empty')

@section('title', __('Forgot password'))
@section('heading', __('Forgot password'))

@section('content')
    <p class="mb-6 text-sm text-gray-500">
        {{ __('Enter your email address and we\'ll send you a password reset link.') }}
    </p>

    <form
        method="POST"
        action="{{ route('forgot-password.submit') }}"
        data-externally-blocked="1"
    >
        @csrf

        <x-form.input
            name="email"
            type="email"
            :label="__('Email')"
            required
            autofocus
        />

        <x-turnstile/>

        <div class="mt-6">
            <x-cooldown-button
                storage-prefix="password_reset"
                :label="__('Send reset link')"
                :countdown-label="__('Send again in')"
                :restart="session('passwordResetSent', false)"
                disabled
                class="w-full"
            />
        </div>

        <div class="mt-6 text-center text-sm">
            <a href="{{ route('login') }}" class="text-blue-600 hover:underline">
                {{ __('Back to login') }}
            </a>
        </div>
    </form>
@endsection
