@extends('layouts.app')

@section('title', 'Settings')
@section('page-title', 'Settings')
@section('page-subtitle', 'Manage your personal settings.')

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
    <form
        method="POST"
        action="{{ route('settings.update') }}"
        class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200"
    >
        @csrf
        @method('PATCH')

        <x-form.input
            name="name"
            label="Name"
            :value="auth()->user()->name"
            required
            autofocus
            autocomplete="name"
        />

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
                Save
            </button>
        </div>
    </form>
@endsection
