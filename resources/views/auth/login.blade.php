@extends('layouts.empty')

@section('title', 'Login')

@section('content')
    <div class="w-full max-w-md bg-white rounded-lg shadow p-8">
        <h1 class="text-2xl font-semibold mb-6">
            Login
        </h1>

        @if (session('status'))
            <div class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.submit') }}">
            @csrf

            <x-form.input
                name="email"
                type="email"
                label="Email"
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

            <button
                type="submit"
                class="w-full rounded-md bg-gray-900 px-4 py-2 text-white hover:bg-gray-700"
            >
                Login
            </button>
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
