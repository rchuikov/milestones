@props([
    'name',
    'label' => null,
    'value' => null,
])

@php
    $inputId = $attributes->get('id', $name);
    $pickerId = $inputId . '_picker';
    $previewId = $inputId . '_preview';
    $colorValue = old($name, $value);
@endphp

<div>
    @if ($label)
        <label for="{{ $inputId }}" class="block text-sm font-medium text-gray-900">
            {{ $label }}
        </label>
    @endif

    <div
        class="mt-1 flex h-11 w-full overflow-hidden rounded-lg border border-gray-300 bg-white shadow-sm
           focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500"
    >
        <label
            for="{{ $pickerId }}"
            class="flex w-12 shrink-0 cursor-pointer items-center justify-center border-r border-gray-300"
        >
        <span
            id="{{ $previewId }}"
            class="h-6 w-6 rounded border border-gray-400"
            @if ($colorValue)
                style="background-color: {{ $colorValue }}"
            @endif
        ></span>

            <input
                id="{{ $pickerId }}"
                type="color"
                @if ($colorValue)
                    value="{{ $colorValue }}"
                @endif
                class="sr-only"
            >
        </label>

        <input
            id="{{ $inputId }}"
            name="{{ $name }}"
            type="text"
            value="{{ $colorValue }}"
            placeholder="No color"
            maxlength="7"
            class="min-w-0 flex-1 border-0 px-3 text-sm text-gray-900 focus:ring-0"
        >

        <button
            id="{{ $inputId }}_clear"
            type="button"
            class="flex w-11 shrink-0 items-center justify-center border-l border-gray-300 text-gray-400 hover:text-gray-700"
            title="Clear color"
        >
            <x-heroicon-o-x-mark class="size-5"/>
        </button>
    </div>

    @error($name)
    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

@push('scripts')
    <script>
        (() => {
            const colorPicker = document.getElementById(@js($pickerId));
            const colorInput = document.getElementById(@js($inputId));
            const colorPreview = document.getElementById(@js($previewId));
            const clearButton = document.getElementById(@js($inputId . '_clear'));

            colorPicker.addEventListener('input', () => {
                colorInput.value = colorPicker.value;
                colorPreview.style.backgroundColor = colorPicker.value;
            });

            colorInput.addEventListener('input', () => {
                if (/^#[0-9A-Fa-f]{6}$/.test(colorInput.value)) {
                    colorPicker.value = colorInput.value;
                    colorPreview.style.backgroundColor = colorInput.value;
                }
            });

            clearButton.addEventListener('click', () => {
                colorInput.value = '';
                colorPicker.value = '#000000';
                colorPreview.style.backgroundColor = '';
                colorInput.focus();
            });
        })();
    </script>
@endpush
