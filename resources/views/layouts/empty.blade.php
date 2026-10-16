<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', config('app.name'))</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-gray-100 text-gray-900">

<main class="flex min-h-screen items-center justify-center px-4 py-8">
    <div class="w-full max-w-md">
        <a
            href="/"
            class="mb-6 flex items-center justify-center gap-3"
        >
            <img
                src="{{ asset('images/logo.png') }}"
                alt="{{ config('app.name') }}"
                class="h-18 w-18"
            >
            <span class="text-2xl font-semibold">
                <span class="text-sky-600">My</span>
                <span class="text-blue-800">Milestones</span>
            </span>
        </a>

        <x-flash-message/>

        @yield('content')
    </div>
</main>

@stack('scripts')
</body>
</html>
