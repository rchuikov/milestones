@props([
    'name',
    'label' => null,
    'checked' => false,
])
<div class="mb-4">
    <label class="flex items-center gap-2">
        <input
            type="checkbox"
            name="{{ $name }}"
            value="1"
            @checked($checked)
            {{ $attributes->merge([
                'class' => 'rounded border-gray-300',
            ]) }}
        >

        @if ($label)
            <span class="text-sm text-gray-700">
            {{ $label }}
        </span>
        @endif
    </label>
</div>
