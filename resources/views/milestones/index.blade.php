@extends('layouts.app')

@section('page-title', __('My milestones'))
@section('page-subtitle', __('Your current progress at a glance.'))

@section('page-actions')
    <a
        href="{{ route('milestones.create') }}"
        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"
    >
        <x-heroicon-o-plus class="size-5"/>
        {{ __('Create milestone') }}
    </a>
@endsection

@section('content')
    <div data-error-page-url="{{ route('error') }}" hidden></div>

    <div
        id="today-anniversaries"
        data-url="{{ route('milestones.anniversaries') }}"
    >
        @include('milestones._today-anniversaries', [
            'milestones' => $milestones,
        ])
    </div>

    <div class="space-y-4">
        @forelse ($milestones as $milestone)
            @include('milestones._card', compact('milestone'))
        @empty
            <div class="rounded-xl bg-white p-10 text-center shadow-sm ring-1 ring-gray-200">
                <x-heroicon-o-flag class="mx-auto size-10 text-gray-400"/>

                <h2 class="mt-3 text-base font-semibold text-gray-900">
                    {{ __('No milestones yet') }}
                </h2>

                <p class="mt-1 text-sm text-gray-500">
                    {{ __('Create your first milestone to start tracking progress.') }}
                </p>

                <a
                    href="{{ route('milestones.create') }}"
                    class="mt-5 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                >
                    <x-heroicon-o-plus class="size-5"/>
                    {{ __('Create milestone') }}
                </a>
            </div>
        @endforelse
    </div>
@endsection

@push('scripts')
    @vite([
        'resources/js/timer.js',
        'resources/js/milestone-actions.js',
    ])
@endpush
