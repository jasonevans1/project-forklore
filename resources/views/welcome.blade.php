<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="bg-page text-ink min-h-screen flex flex-col font-sans">

        {{-- Header --}}
        <header class="w-full px-4 py-4 flex items-center justify-between max-w-4xl mx-auto">
            <span class="text-xl font-semibold tracking-tight uppercase font-display">Forklore</span>

            @if (Route::has('login'))
                <nav class="flex items-center gap-3">
                    @auth
                        <a
                            href="{{ route('dashboard') }}"
                            class="inline-block px-5 py-1.5 border border-ticket-line hover:border-accent rounded-sm text-sm leading-normal"
                        >
                            Dashboard
                        </a>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="inline-block px-5 py-1.5 border border-transparent hover:border-ticket-line rounded-sm text-sm leading-normal"
                        >
                            Log in
                        </a>

                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                                class="inline-block px-5 py-1.5 bg-accent text-accent-foreground hover:opacity-90 border border-accent rounded-sm text-sm leading-normal"
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
            <h1 class="text-4xl sm:text-5xl font-semibold tracking-tight mb-4 uppercase font-display">Forklore</h1>
            <p class="text-lg sm:text-xl text-ink/70 mb-10 leading-relaxed">
                End the &ldquo;I don&rsquo;t know, what do you want?&rdquo; conversation.
            </p>

            @guest
                <div class="flex flex-col sm:flex-row gap-3 mb-16">
                    @if (Route::has('login'))
                        <a
                            href="{{ route('login') }}"
                            class="inline-block px-6 py-2.5 border border-ticket-line hover:border-accent rounded-sm text-sm font-medium leading-normal"
                        >
                            Log in
                        </a>
                    @endif

                    @if (Route::has('register'))
                        <a
                            href="{{ route('register') }}"
                            class="inline-block px-6 py-2.5 bg-accent text-accent-foreground hover:opacity-90 border border-accent rounded-sm text-sm font-medium leading-normal"
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
                        class="inline-block px-6 py-2.5 bg-accent text-accent-foreground hover:opacity-90 border border-accent rounded-sm text-sm font-medium leading-normal"
                    >
                        Dashboard
                    </a>
                </div>
            @endauth

            {{-- Four Decision Modes --}}
            <div class="flex flex-col w-full text-left divide-y divide-dashed divide-ticket-line border-t border-b border-dashed border-ticket-line">
                @foreach ([
                    ['number' => '01', 'name' => 'Quick Pick', 'description' => 'One tap. Weather-aware, time-aware, skips recently visited places.'],
                    ['number' => '02', 'name' => 'Something Happening Tonight', 'description' => 'Filters to restaurants with live events starting soon.'],
                    ['number' => '03', 'name' => 'Guided Quiz', 'description' => '5 questions scored against favorites, returns the best match.'],
                    ['number' => '04', 'name' => 'Tournament', 'description' => 'Head-to-head bracket of 4 or 8 favorites until one wins.'],
                ] as $mode)
                    <div class="flex items-baseline gap-4 py-4">
                        <span class="text-sm text-accent font-mono-ticket">{{ $mode['number'] }}</span>
                        <div>
                            <h2 class="font-semibold mb-1 font-display uppercase">{{ $mode['name'] }}</h2>
                            <p class="text-sm text-ink/70">{{ $mode['description'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </main>

        <footer class="py-6 text-center text-xs text-ink/50 font-mono-ticket">
            &copy; {{ date('Y') }} Forklore. Every decision ends with one restaurant.
        </footer>

    </body>
</html>
