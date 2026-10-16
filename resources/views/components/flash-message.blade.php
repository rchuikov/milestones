@php
    $flash = session('flash');

    $type = $flash['type'] ?? null;
    $message = $flash['message'] ?? null;

    [$classes, $icon] = match ($type) {
        'success' => [
            'border-green-200 bg-green-50 text-green-800',
            'heroicon-o-check-circle',
        ],
        'warning' => [
            'border-amber-200 bg-amber-50 text-amber-900',
            'heroicon-o-exclamation-triangle',
        ],
        'danger' => [
            'border-red-300 bg-red-50 text-red-900',
            'heroicon-o-x-circle',
        ],
        'info' => [
            'border-sky-200 bg-sky-50 text-sky-800',
            'heroicon-o-information-circle',
        ],
        default => [null, null],
    };
@endphp

@if (is_string($message) && $message !== '' && $classes)
    <div
        class="mb-6 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm {{ $classes }}"
        role="{{ in_array($type, ['danger', 'warning'], true) ? 'alert' : 'status' }}"
    >
        <x-dynamic-component
            :component="$icon"
            class="mt-0.5 size-5 shrink-0"
            aria-hidden="true"
        />

        <div class="min-w-0">
            {{ $message }}
        </div>
    </div>
@endif
