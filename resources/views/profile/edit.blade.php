@extends('layouts.app')

@section('title', 'Profile')
@section('page-title', 'Edit profile')
@section('page-subtitle', 'Update your account information or change your password.')

@section('page-actions')
    <a
        href="{{ route('milestones.index') }}"
        class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900"
    >
        <x-heroicon-o-arrow-left class="size-4"/>
        Back
    </a>
@endsection

@section('content')
    @php
        $isSettingPassword = auth()->user()->password === null;
    @endphp

    <form
        method="POST"
        action="{{ route('profile.update') }}"
        class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200"
    >
        @csrf
        @method('PATCH')

        <div class="space-y-5">
            <x-form.input
                name="name"
                label="Name"
                :value="auth()->user()->name"
                :required="! $isSettingPassword"
                :disabled="$isSettingPassword"
                :autofocus="! $isSettingPassword"
                class="disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                autocomplete="name"
            />

            <x-form.input
                name="email"
                type="email"
                label="Email"
                :value="auth()->user()->email"
                :required="! $isSettingPassword"
                :disabled="$isSettingPassword"
                class="disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                autocomplete="email"
            />
        </div>

        <div class="mt-8 border-t border-gray-200 pt-6">
            <h2 class="text-base font-semibold text-gray-900">
                Password
            </h2>

            <div class="mt-5 space-y-5">
                @if ($isSettingPassword)
                    <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                        <p class="font-semibold">Set a password to edit your profile</p>
                        <p class="mt-1">
                            You will also be able to sign in with your email.
                            Google sign-in will remain available.
                        </p>
                    </div>
                @else
                    <div>
                        <x-form.input
                            name="current_password"
                            type="password"
                            label="Current password"
                            autocomplete="current-password"
                        />

                        <p class="-mt-2 text-xs text-gray-500">
                            Required when changing your email or password.
                        </p>
                    </div>
                @endif

                <x-form.input
                    name="password"
                    type="password"
                    label="New password"
                    autocomplete="new-password"
                    :required="$isSettingPassword"
                    :autofocus="$isSettingPassword"
                />

                <x-form.input
                    name="password_confirmation"
                    type="password"
                    label="Confirm new password"
                    autocomplete="new-password"
                    :required="$isSettingPassword"
                />
            </div>
        </div>

        <div class="mt-8 flex justify-end gap-3 border-t border-gray-200 pt-6">
            <a
                href="{{ route('milestones.index') }}"
                class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
            >
                <x-heroicon-o-check class="size-5"/>
                {{ $isSettingPassword ? 'Set password' : 'Save' }}
            </button>
        </div>
    </form>
@endsection
