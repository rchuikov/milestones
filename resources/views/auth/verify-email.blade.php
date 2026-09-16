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

        <div class="mt-6 flex items-center justify-between gap-4">
            <form method="POST" action="{{ route('verification.resend') }}">
                @csrf

                <button
                    type="submit"
                    class="cursor-pointer text-sm text-blue-600 hover:underline"
                >
                    Resend verification email
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button
                    type="submit"
                    class="cursor-pointer text-sm text-gray-600 hover:text-gray-950"
                >
                    Logout
                </button>
            </form>
        </div>
    </div>
@endsection
