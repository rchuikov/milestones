@props([
    'name',
    'label',
    'options' => [],
    'value' => null,
])

@php
    $selected = old($name, $value);
@endphp

<div>
    <label
        for="{{ $name }}"
        class="mb-2 block text-sm font-medium text-gray-700"
    >
        {{ $label }}
    </label>

    <select
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->class([
            'w-full rounded-lg border bg-white px-3 py-2.5 text-sm text-gray-900',
            'focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20',
            'border-red-500' => $errors->has($name),
            'border-gray-300' => ! $errors->has($name),
        ]) }}
    >
        @foreach ($options as $optionValue => $optionLabel)
            <option
                value="{{ $optionValue }}"
                @selected((string) $selected === (string) $optionValue)
            >
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>

    @error($name)
    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
