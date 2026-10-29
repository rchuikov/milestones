@php
    $code = (int) request()->query('code', 500);

    [$title, $message] = match ($code) {
        401 => [
            __('Authentication required'),
            __('Please log in again to continue.'),
        ],
        403 => [
            __('Access denied'),
            __('You do not have permission to perform this action.'),
        ],
        419 => [
            __('Session expired'),
            __('Please refresh the page or log in again.'),
        ],
        404 => [
            __('Page not found'),
            __('The requested resource could not be found.'),
        ],
        default => [
            __('Something went wrong'),
            __('An error occurred while processing your request.'),
        ],
    };
@endphp

    <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $code }} — {{ $title }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-red-500/5 text-gray-900">
<main class="mx-auto flex min-h-screen max-w-xl items-center justify-center px-4 py-12">
    <div class="w-full rounded-xl bg-white/50 p-8 text-center shadow-sm ring-1 ring-red-500/20">
        <x-heroicon-o-exclamation-triangle class="mx-auto size-10 text-red-500"/>

        <h1 class="mt-4 text-2xl font-semibold">
            {{ $code }} — {{ $title }}
        </h1>

        <p class="mt-3 text-gray-600">
            {{ $message }}
        </p>

        <div class="mt-6 flex flex-wrap justify-center gap-3">
            @guest
                <a
                    href="{{ route('login') }}"
                    class="inline-flex rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                >
                    {{ __('Log in') }}
                </a>
            @else
                <a
                    href="{{ route('milestones.index') }}"
                    class="inline-flex rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                >
                    {{ __('My milestones') }}
                </a>
            @endguest
        </div>
    </div>
</main>
</body>
</html>
