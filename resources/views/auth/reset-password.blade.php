@extends('layouts.empty')

@section('title', 'Reset password')

@section('content')
    <div class="rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
        <h1 class="mb-6 text-2xl font-semibold">
            Reset password
        </h1>

        <form method="POST" action="{{ route('reset-password.submit') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <x-form.input
                name="email"
                type="email"
                label="Email"
                :value="$email"
                required
            />

            <x-form.input
                name="password"
                type="password"
                label="New password"
                required
                autofocus
            />

            <x-form.input
                name="password_confirmation"
                type="password"
                label="Confirm password"
                required
            />

            <button
                type="submit"
                class="mt-2 w-full cursor-pointer rounded-md bg-gray-900 px-4 py-2 text-white hover:bg-gray-800"
            >
                Reset password
            </button>
        </form>
    </div>
@endsection
