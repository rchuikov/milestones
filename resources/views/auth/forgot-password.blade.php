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

            <button
                type="submit"
                class="mt-2 w-full cursor-pointer rounded-md bg-gray-900 px-4 py-2 text-white hover:bg-gray-800"
            >
                Send reset link
            </button>

            <div class="mt-6 text-center text-sm">
                <a href="{{ route('login') }}" class="text-blue-600 hover:underline">
                    Back to login
                </a>
            </div>
        </form>
    </div>
@endsection
