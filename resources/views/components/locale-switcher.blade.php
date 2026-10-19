
<form
    method="POST"
    action="{{ route('locale.update') }}"
    class="flex max-w-full shrink-0 flex-wrap items-center justify-end gap-2 self-end"
>
    @csrf

    @foreach (\App\Enums\Locale::cases() as $locale)
        @if (! $loop->first)
            <span class="text-gray-300">|</span>
        @endif

        @if (app()->getLocale() === $locale->value)
            <span class="text-sm font-semibold text-gray-900" aria-current="true">
                {{ $locale->label() }}
            </span>
        @else
            <button
                type="submit"
                name="locale"
                value="{{ $locale->value }}"
                class="cursor-pointer text-sm text-gray-400 hover:text-gray-700"
            >
                {{ $locale->label() }}
            </button>
        @endif
    @endforeach
</form>
