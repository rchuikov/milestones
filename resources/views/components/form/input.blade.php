@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
])

<div class="mb-4">
    @if ($label)
        <label for="{{ $name }}"
               class="block text-sm font-medium mb-1">
            {{ $label }}

            @if ($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $type !== 'password' ? old($name, $value) : null }}"
        @required($required)
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
