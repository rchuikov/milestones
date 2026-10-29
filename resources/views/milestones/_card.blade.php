@php
    /**
     * @var \App\Models\Milestone $milestone
     */

    $currentPeriod = $milestone->currentPeriod;
    $error = $error ?? null;
@endphp

<div
    data-milestone-id="{{ $milestone->id }}"
    class="relative overflow-hidden rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200"
>
    @if ($error)
        <div
            role="alert"
            class="mb-5 rounded-lg border border-red-300 bg-red-500/1 px-4 py-3 text-sm font-medium text-red-600"
        >
            {{ $error }}
        </div>
    @endif
    @if ($milestone->color)
        <div
            class="absolute inset-y-0 right-0 w-2"
            style="background-color: {{ $milestone->color }}"
        ></div>
    @endif
    <div class="flex flex-col gap-6 max-sm:gap-0 sm:grid sm:min-h-32 sm:grid-cols-[minmax(260px,auto)_1fr_auto] sm:items-center sm:gap-6">
        <div class="min-w-0">
            <h2 class="wrap-break-word whitespace-normal text-2xl font-semibold text-gray-900">
                {{ $milestone->name }}
            </h2>

            <div class="mt-1">
                @if ($currentPeriod)
                    <div class="milestone-duration hidden text-lg font-semibold text-gray-600"></div>

                    <div
                        class="milestone-timer whitespace-nowrap text-3xl font-bold tracking-tight text-gray-900"
                        data-started-at="{{ $currentPeriod->started_at->toIso8601String() }}"
                    >
                    </div>

                    <div class="mt-1 flex items-center gap-1 text-base text-gray-500 max-sm:flex-wrap">
                        <span>
                            {{ __('Since :date', ['date' => $currentPeriod->started_at->format('d.m.Y')]) }}
                        </span>

                        <span class="milestone-total-days text-base text-gray-500"></span>
                    </div>

                @else
                    <div class="text-3xl font-semibold text-gray-500">
                        {{ __('Stopped') }}
                    </div>
                @endif
            </div>
        </div>

        <div class="flex min-w-0 flex-wrap items-center justify-center gap-2 max-sm:mt-6 max-sm:[&:not(:has(span))]:hidden">
            @if ($currentPeriod)
                @include('milestones._anniversaries', [
                    'milestone' => $milestone,
                    'currentPeriod' => $currentPeriod,
                ])
            @endif
        </div>

        <div class="flex shrink-0 flex-col items-end justify-end gap-3 max-sm:mt-6 max-sm:w-full max-sm:min-w-0 max-sm:items-center sm:justify-self-end">
            <div class="flex items-center gap-2 max-sm:w-full max-sm:flex-wrap max-sm:justify-center">
                @if ($currentPeriod)
                    <form method="POST" action="{{ route('milestones.reset', $milestone) }}" data-milestone-action>
                        @csrf

                        <button
                            type="submit"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3.5 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100"
                        >
                            <x-heroicon-o-arrow-path class="size-4"/>
                            {{ __('Reset') }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('milestones.stop', $milestone) }}" data-milestone-action>
                        @csrf

                        <button
                            type="submit"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-red-300 bg-red-50 px-3.5 py-2 text-sm font-semibold text-red-700 hover:bg-red-100"
                        >
                            <x-heroicon-o-stop class="size-4"/>
                            {{ __('Stop') }}
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('milestones.start', $milestone) }}" data-milestone-action>
                        @csrf

                        <button
                            type="submit"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-green-300 bg-green-50 px-3.5 py-2 text-sm font-semibold text-green-700 hover:bg-green-100"
                        >
                            <x-heroicon-o-play class="size-4"/>
                            {{ __('Start') }}
                        </button>
                    </form>
                @endif
            </div>

            <div class="flex items-center gap-2 max-sm:w-full max-sm:flex-wrap max-sm:justify-center">
                <a
                    href="{{ route('milestones.history', $milestone) }}"
                    class="inline-flex cursor-pointer items-center gap-1.5 px-2 py-2 text-sm font-medium text-gray-600 hover:text-gray-900"
                >
                    <x-heroicon-o-clock class="size-4"/>
                    {{ __('History') }}
                </a>

                <a
                    href="{{ route('milestones.edit', $milestone) }}"
                    class="inline-flex cursor-pointer items-center gap-1.5 px-2 py-2 text-sm font-medium text-gray-600 hover:text-gray-900"
                >
                    <x-heroicon-o-pencil-square class="size-4"/>
                    {{ __('Edit') }}
                </a>

                <form
                    method="POST"
                    action="{{ route('milestones.destroy', $milestone) }}"
                    onsubmit="return confirm({{ Illuminate\Support\Js::from(__('Delete this milestone?')) }})"
                >
                    @csrf
                    @method('DELETE')

                    <button
                        type="submit"
                        class="inline-flex cursor-pointer items-center gap-1.5 px-2 py-2 text-sm font-medium text-red-600 hover:text-red-800"
                    >
                        <x-heroicon-o-trash class="size-4"/>
                        {{ __('Delete') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
