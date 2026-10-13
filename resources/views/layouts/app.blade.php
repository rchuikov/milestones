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
<div class="flex min-h-screen flex-col">
    <nav class="border-b border-gray-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">

                <a
                    href="{{ route('milestones.index') }}"
                    class="relative flex h-16 items-center gap-3"
                >
                    <img
                        src="{{ asset('images/logo.png') }}"
                        alt="{{ config('app.name') }}"
                        class="relative z-10 h-16 w-16"
                    >

                    <span class="text-xl font-semibold leading-none">
                    <span class="text-sky-600">My</span>
                    <span class="text-blue-800">Milestones</span>
                </span>
                </a>

                <div class="flex items-center gap-4">
                    <details class="relative">
                        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-950">
                            <x-heroicon-o-user-circle class="h-5 w-5"/>
                            <span>
                            {{ auth()->user()->name }}
                            <span class="text-gray-400">
                                ({{ auth()->user()->email }})
                            </span>
                        </span>
                            <x-heroicon-o-chevron-down class="h-4 w-4"/>
                        </summary>
                        <div class="absolute right-0 z-50 mt-2 w-56 rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                            <a href="{{ route('profile') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <x-heroicon-o-cog-6-tooth class="h-5 w-5"/>
                                Profile
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="flex w-full cursor-pointer items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                                >
                                    <x-heroicon-o-arrow-right-start-on-rectangle class="h-4 w-4"/>
                                    Logout
                                </button>
                            </form>
                        </div>
                    </details>
                </div>

            </div>
        </div>
    </nav>

    <main class="flex-1">
        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            @if (trim($__env->yieldContent('page-title')))
                <div class="mb-8 flex items-start justify-between gap-4 px-2">
                    <div>
                        <h1 class="text-3xl font-semibold tracking-tight text-gray-900">
                            @yield('page-title')
                        </h1>

                        @if (trim($__env->yieldContent('page-subtitle')))
                            <p class="mt-1 text-base font-medium text-gray-600">
                                @yield('page-subtitle')
                            </p>
                        @endif
                    </div>

                    @hasSection('page-actions')
                        <div class="shrink-0">
                            @yield('page-actions')
                        </div>
                    @endif
                </div>
            @endif

            @yield('content')
        </div>
    </main>

    <footer class="border-t border-gray-200">
        <div class="mx-auto max-w-5xl px-4 py-6 text-center text-sm text-gray-500 sm:px-6 lg:px-8">
            © {{ now()->year }} Roman Chuikov
            <span class="mx-1">·</span>
            Built with Laravel
        </div>
    </footer>

    @stack('scripts')
</div>
</body>
</html>
