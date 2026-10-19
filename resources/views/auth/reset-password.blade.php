@extends('layouts.empty')

@section('title', __('Reset password'))
@section('heading', __('Reset password'))

@section('content')
        <form method="POST" action="{{ route('reset-password.submit') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <x-form.input
                name="email"
                type="email"
                :label="__('Email')"
                :value="$email"
                required
            />

            <x-form.input
                name="password"
                type="password"
                :label="__('New password')"
                required
                autofocus
            />

            <x-form.input
                name="password_confirmation"
                type="password"
                :label="__('Confirm password')"
                required
            />

            <button
                type="submit"
                class="mt-2 w-full cursor-pointer rounded-md bg-gray-900 px-4 py-2 text-white hover:bg-gray-800"
            >
                {{ __('Reset password') }}
            </button>
        </form>
@endsection
