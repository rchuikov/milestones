@php
    /**
     * @var \App\Models\Milestone $milestone
     * @var \App\Models\MilestonePeriod|null $period
     * @var bool $canEditStartedAt
     */
        $isEdit = $milestone->exists;
@endphp

@extends('layouts.app')

@section('page-title', $isEdit ? __('Edit milestone') : __('Create milestone'))
@section('page-subtitle', $milestone->name ?? '')

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
    <form
        method="POST"
        action="{{ $isEdit
                ? route('milestones.update', $milestone)
                : route('milestones.store') }}"
        class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200"
    >
        @csrf

        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="space-y-5">
            <x-form.input
                name="name"
                :label="__('Name')"
                :value="$milestone->name"
                required
            />

            @unless (! $canEditStartedAt)
                <x-form.input
                    name="started_at"
                    :label="__('Started at')"
                    type="datetime-local"
                    :value="$period?->started_at->format('Y-m-d\TH:i')"
                    required
                />
            @endunless

            <x-form.color-input
                name="color"
                :label="__('Color')"
                :value="$milestone->color"
            />
        </div>

        <div class="mt-8 border-t border-gray-200 pt-6">
            <h2 class="text-base font-semibold text-gray-900">
                {{ __('Special dates') }}
            </h2>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <x-form.checkbox
                    name="show_round_days"
                    :label="__('Round days')"
                    :checked="old('show_round_days', $milestone->show_round_days ?? true)"
                />

                <x-form.checkbox
                    name="show_half_decade_days"
                    :label="__('Days ending in 5')"
                    :checked="old('show_half_decade_days', $milestone->show_half_decade_days ?? true)"
                />

                <x-form.checkbox
                    name="show_round_weeks"
                    :label="__('Full weeks')"
                    :checked="old('show_round_weeks', $milestone->show_round_weeks ?? true)"
                />

                <x-form.checkbox
                    name="show_round_months"
                    :label="__('Full months')"
                    :checked="old('show_round_months', $milestone->show_round_months ?? true)"
                />

                <x-form.checkbox
                    name="show_years"
                    :label="__('Anniversaries')"
                    :checked="old('show_years', $milestone->show_years ?? true)"
                />
            </div>
        </div>

        <div class="mt-8 flex justify-end gap-3 border-t border-gray-200 pt-6">
            <a
                href="{{ route('milestones.index') }}"
                class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
            >
                {{ __('Cancel') }}
            </a>

            <button
                type="submit"
                class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
            >
                @if ($isEdit)
                    <x-heroicon-o-check class="size-5"/>
                    {{ __('Save') }}
                @else
                    <x-heroicon-o-plus class="size-5"/>
                    {{ __('Create') }}
                @endif
            </button>
        </div>
    </form>
@endsection
