<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', config('app.name'))</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="MyMilestones">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-gray-100 text-gray-900">

<main class="flex min-h-screen items-center justify-center px-4 py-8 max-sm:px-2">
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

        <div class="rounded-lg border border-gray-200 bg-white p-8 pt-4 shadow-sm max-sm:p-4 max-sm:px-3">
            <div class="mb-6 flex flex-col items-start gap-1">
                <x-locale-switcher/>

                <h1 class="min-w-0 wrap-break-word text-2xl font-semibold">
                    @yield('heading')
                </h1>
            </div>

            @yield('content')
        </div>
    </div>
</main>

@stack('scripts')
</body>
</html>
