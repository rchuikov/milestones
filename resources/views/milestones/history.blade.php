@php
    use App\Models\MilestonePeriod;

/**
 * @var MilestonePeriod[] $periods
 */

    $now = now();

    $currentPeriod = $periods->firstWhere('finished_at', null);

    $totalSeconds = $periods->sum(
        fn (MilestonePeriod $period) =>
            $period->started_at->diffInSeconds(
                $period->finished_at ?? $now,
                true,
            )
    );
@endphp

@extends('layouts.app')

@section('page-title', __('History'))
@section('page-subtitle', $milestone->name)

@section('page-actions')
    <a
        href="{{ route('milestones.index') }}"
        class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900"
    >
        <x-heroicon-o-arrow-left class="size-4"/>
        {{ __('Back') }}
    </a>
@endsection

@section('content')
    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <div class="text-sm font-medium text-gray-500">
                {{ __('Total') }}
            </div>

            <div class="mt-1 text-xl font-semibold text-gray-900">
                <x-duration :seconds="$totalSeconds"/>
            </div>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <div class="text-sm font-medium text-gray-500">
                {{ __('Current period') }}
            </div>

            <div class="mt-1 text-xl font-semibold text-gray-900">
                @if ($currentPeriod)
                    <x-duration :seconds="$currentPeriod->started_at->diffInSeconds($now, true)"/>
                @else
                    {{ __('Stopped') }}
                @endif
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        @forelse ($periods as $period)
            @php
                $finishedAt = $period->finished_at ?? $now;
            @endphp

            <div class="flex items-center justify-between gap-6 border-b border-gray-100 px-6 py-5 last:border-b-0">
                <div>
                    <div class="text-lg font-semibold text-gray-900">
                        {{ $period->started_at->format('d.m.Y H:i') }}
                    </div>

                    <div class="mt-1 text-base text-gray-600">
                        @if ($period->finished_at)
                            {{ __('Finished :date', ['date' => $period->finished_at->format('d.m.Y H:i')]) }}
                        @else
                            <span class="font-medium text-green-700">
                                    {{ __('Active') }}
                                </span>
                        @endif
                    </div>
                </div>

                <div class="text-right">
                    <div class="font-medium text-gray-700">
                        <x-duration :seconds="$period->started_at->diffInSeconds($finishedAt, true)"/>
                    </div>
                </div>
            </div>
        @empty
            <div class="p-10 text-center text-sm text-gray-500">
                {{ __('No history yet.') }}
            </div>
        @endforelse
    </div>
@endsection
