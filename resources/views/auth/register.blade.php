@extends('layouts.empty')

@section('title', __('Register'))
@section('heading', __('Register'))

@section('content')
    <form
        method="POST"
        action="{{ route('register.submit') }}"
        data-externally-blocked="1"
    >
        @csrf

        <x-form.input
            name="name"
            :label="__('Name')"
            required
            autofocus
        />

        <x-form.input
            name="email"
            type="email"
            :label="__('Email')"
            required
        />

        <x-form.input
            name="password"
            type="password"
            :label="__('Password')"
            required
        />

        <x-form.input
            name="password_confirmation"
            type="password"
            :label="__('Confirm password')"
            required
        />

        <x-turnstile/>

        <button
            type="submit"
            disabled
            class="mt-2 w-full cursor-pointer rounded-md bg-gray-900 px-4 py-2 text-white hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{ __('Register') }}
        </button>

        <div class="mt-6 text-center text-sm">
            <a
                href="{{ route('login') }}"
                class="text-blue-600 hover:underline"
            >
                {{ __('Already have an account?') }}
            </a>
        </div>
    </form>
@endsection
