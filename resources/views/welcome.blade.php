<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="bg-white dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 min-h-screen flex flex-col font-sans">

        {{-- Header --}}
        <header class="w-full px-4 py-4 flex items-center justify-between max-w-4xl mx-auto">
            <span class="text-xl font-semibold tracking-tight">Forklore</span>

            @if (Route::has('login'))
                <nav class="flex items-center gap-3">
                    @auth
                        <a
                            href="{{ route('dashboard') }}"
                            class="inline-block px-5 py-1.5 border border-zinc-300 dark:border-zinc-700 hover:border-zinc-500 dark:hover:border-zinc-500 rounded-sm text-sm leading-normal dark:text-zinc-100"
                        >
                            Dashboard
                        </a>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="inline-block px-5 py-1.5 border border-transparent hover:border-zinc-300 dark:hover:border-zinc-700 rounded-sm text-sm leading-normal dark:text-zinc-100"
                        >
                            Log in
                        </a>

                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                                class="inline-block px-5 py-1.5 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 hover:bg-zinc-700 dark:hover:bg-white border border-zinc-900 dark:border-zinc-100 rounded-sm text-sm leading-normal"
                            >
                                Register
                            </a>
                        @endif
                    @endauth
                </nav>
            @endif
        </header>

        {{-- Hero --}}
        <main class="flex-1 flex flex-col items-center justify-center px-4 py-16 text-center max-w-2xl mx-auto w-full">
            <h1 class="text-4xl sm:text-5xl font-semibold tracking-tight mb-4">Forklore</h1>
            <p class="text-lg sm:text-xl text-zinc-600 dark:text-zinc-400 mb-10 leading-relaxed">
                End the &ldquo;I don&rsquo;t know, what do you want?&rdquo; conversation.
            </p>

            @guest
                <div class="flex flex-col sm:flex-row gap-3 mb-16">
                    @if (Route::has('login'))
                        <a
                            href="{{ route('login') }}"
                            class="inline-block px-6 py-2.5 border border-zinc-300 dark:border-zinc-700 hover:border-zinc-500 dark:hover:border-zinc-500 rounded-sm text-sm font-medium leading-normal dark:text-zinc-100"
                        >
                            Log in
                        </a>
                    @endif

                    @if (Route::has('register'))
                        <a
                            href="{{ route('register') }}"
                            class="inline-block px-6 py-2.5 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 hover:bg-zinc-700 dark:hover:bg-white border border-zinc-900 dark:border-zinc-100 rounded-sm text-sm font-medium leading-normal"
                        >
                            Register
                        </a>
                    @endif
                </div>
            @endguest

            @auth
                <div class="flex gap-3 mb-16">
                    <a
                        href="{{ route('dashboard') }}"
                        class="inline-block px-6 py-2.5 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 hover:bg-zinc-700 dark:hover:bg-white border border-zinc-900 dark:border-zinc-100 rounded-sm text-sm font-medium leading-normal"
                    >
                        Dashboard
                    </a>
                </div>
            @endauth

            {{-- Four Decision Modes --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 w-full text-left">
                <div class="p-5 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900">
                    <h2 class="font-semibold mb-1">Quick Pick</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">One tap. Weather-aware, time-aware, skips recently visited places.</p>
                </div>
                <div class="p-5 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900">
                    <h2 class="font-semibold mb-1">Tonight</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Filters to restaurants with live events &mdash; trivia, music, specials &mdash; starting soon.</p>
                </div>
                <div class="p-5 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900">
                    <h2 class="font-semibold mb-1">Guided Quiz</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Five quick questions scored against your favorites. Returns the best match.</p>
                </div>
                <div class="p-5 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900">
                    <h2 class="font-semibold mb-1">Tournament</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Head-to-head bracket of 4 or 8 favorites. Tap the winner until one remains.</p>
                </div>
            </div>
        </main>

        <footer class="py-6 text-center text-xs text-zinc-400 dark:text-zinc-600">
            &copy; {{ date('Y') }} Forklore. Every decision ends with one restaurant.
        </footer>

    </body>
</html>
