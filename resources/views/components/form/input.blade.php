@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
])

<div class="mb-4">
    @if ($label)
        <label for="{{ $name }}"
               class="block text-sm font-medium mb-1">
            {{ $label }}
        </label>
    @endif

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $type !== 'password' ? old($name, $value) : null }}"
        {{ $attributes->merge([
            'class' => 'w-full rounded-md border border-gray-300 px-3 py-2',
        ]) }}
    >

    @error($name)
    <div class="mt-1 text-sm text-red-600">
        {{ $message }}
    </div>
    @enderror
</div>
