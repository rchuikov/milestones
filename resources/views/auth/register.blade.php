@extends('layouts.empty')

@section('title', 'Register')

@section('content')
    <div class="rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
        <h1 class="mb-6 text-2xl font-semibold">
            Register
        </h1>

        <form
            method="POST"
            action="{{ route('register.submit') }}"
            data-externally-blocked="1"
        >
            @csrf

            <x-form.input
                name="name"
                label="Name"
                required
                autofocus
            />

            <x-form.input
                name="email"
                type="email"
                label="Email"
                required
            />

            <x-form.input
                name="password"
                type="password"
                label="Password"
                required
            />

            <x-form.input
                name="password_confirmation"
                type="password"
                label="Confirm password"
                required
            />

            <x-turnstile />

            <button
                type="submit"
                disabled
                class="mt-2 w-full cursor-pointer rounded-md bg-gray-900 px-4 py-2 text-white hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Register
            </button>

            <div class="mt-6 text-center text-sm">
                <a
                    href="{{ route('login') }}"
                    class="text-blue-600 hover:underline"
                >
                    Already have an account?
                </a>
            </div>
        </form>
    </div>
@endsection
