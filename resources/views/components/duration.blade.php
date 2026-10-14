@php
    $seconds = (int) $seconds;
    $days = intdiv($seconds, 86400);
@endphp

<span>
    @if ($seconds < 60)
        less than a minute
    @else
        {{ \Carbon\CarbonInterval::seconds($seconds - ($seconds % 60))
            ->cascade()
            ->forHumans([
                'parts' => 4,
                'short' => false,
                'join' => ', ',
            ]) }}
    @endif

    <span class="whitespace-nowrap text-gray-500">
        ({{ $days }} {{ $days === 1 ? 'day' : 'days' }})
    </span>
</span>
