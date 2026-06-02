<?php

use App\Enums\ModeUsed;
use App\Models\Restaurant;
use App\Models\Visit;
use App\Models\HouseholdState;
use App\Services\QuizAnswers;
use App\Services\QuizService;
use App\Services\WeatherData;
use App\Services\WeatherService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Guided Quiz')] class extends Component {
    /** Current state: 'questions' | 'result' | 'empty' */
    public string $state = 'questions';

    /** Current wizard step (1–5). */
    public int $step = 1;

    /** ID of the top-matched restaurant. */
    public ?int $restaurantId = null;

    // Quiz answer fields — populated one per step.
    public ?string $energy = null;
    public ?string $hunger = null;
    public ?string $familiarity = null;
    public ?string $distance = null;
    public ?string $cuisine = null;

    /** User latitude — set by Alpine via Geolocation API. */
    public ?float $lat = null;

    /** User longitude — set by Alpine via Geolocation API. */
    public ?float $lng = null;

    // -------------------------------------------------------------------------
    // Computed
    // -------------------------------------------------------------------------

    #[Computed]
    public function restaurant(): ?Restaurant
    {
        return $this->restaurantId ? Restaurant::find($this->restaurantId) : null;
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    /**
     * Store the answer for the current step and advance — resolving the result
     * on step 5.
     */
    public function answer(string $field, mixed $value): void
    {
        $this->{$field} = $value;

        if ($this->step < 5) {
            $this->step++;

            return;
        }

        // All 5 answers collected — run the quiz.
        $this->resolve();
    }

    /**
     * Show the runner-up in place of the current pick.
     */
    public function reject(): void
    {
        $winner = $this->restaurant;

        if ($winner === null) {
            $this->state = 'empty';

            return;
        }

        $runnerUp = app(QuizService::class)->runnerUp(
            Auth::user(),
            $this->buildAnswers(),
            $winner,
            $this->resolveWeather(),
        );

        if ($runnerUp === null) {
            $this->state = 'empty';
            $this->restaurantId = null;

            return;
        }

        $this->restaurantId = $runnerUp->id;
        unset($this->restaurant);
    }

    /**
     * Log a visit and redirect to the dashboard.
     */
    public function going(): void
    {
        $restaurant = Restaurant::findOrFail($this->restaurantId);

        Visit::create([
            'user_id' => Auth::id(),
            'restaurant_id' => $restaurant->id,
            'visited_at' => now(),
            'mode_used' => ModeUsed::Quiz,
        ]);

        $restaurant->increment('visit_count');
        $restaurant->update(['last_visited_at' => now()]);
        HouseholdState::recordPick(Auth::user());

        Flux::toast(variant: 'success', text: __('Enjoy your meal! 🍽️'));

        $this->redirect(route('dashboard'), navigate: true);
    }

    /**
     * Reset wizard back to step 1.
     */
    public function restart(): void
    {
        $this->state = 'questions';
        $this->step = 1;
        $this->restaurantId = null;
        $this->energy = null;
        $this->hunger = null;
        $this->familiarity = null;
        $this->distance = null;
        $this->cuisine = null;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolve(): void
    {
        $restaurant = app(QuizService::class)->topMatch(
            Auth::user(),
            $this->buildAnswers(),
            $this->resolveWeather(),
        );

        if ($restaurant === null) {
            $this->state = 'empty';

            return;
        }

        $this->restaurantId = $restaurant->id;
        $this->state = 'result';
    }

    private function buildAnswers(): QuizAnswers
    {
        return new QuizAnswers(
            energy: $this->energy ?? 'moderate',
            hunger: $this->hunger ?? 'moderate',
            familiarity: $this->familiarity ?? 'either',
            distance: $this->distance ?? 'anywhere',
            cuisine: $this->cuisine,
            lat: $this->lat,
            lng: $this->lng,
        );
    }

    private function resolveWeather(): ?WeatherData
    {
        if ($this->lat === null || $this->lng === null) {
            return null;
        }

        return app(WeatherService::class)->fetch($this->lat, $this->lng);
    }
}; ?>

<div
    class="flex min-h-[calc(100dvh-4rem)] flex-col"
    x-data
    x-init="
        if ('geolocation' in navigator) {
            navigator.geolocation.getCurrentPosition(
                pos => { $wire.lat = pos.coords.latitude; $wire.lng = pos.coords.longitude; },
                () => {}
            )
        }
    "
>

    {{-- ---------------------------------------------------------------- --}}
    {{-- QUESTIONS — 5-step wizard                                         --}}
    {{-- ---------------------------------------------------------------- --}}
    @if ($state === 'questions')

        {{-- Turn indicator --}}
        @php $partner = Auth::user()->partner; @endphp
        @if ($partner)
            <p class="px-6 pt-4 text-sm text-neutral-400 dark:text-neutral-500">
                @if (\App\Models\HouseholdState::isPartnersTurn(Auth::user()))
                    {{ __('Partner\'s turn') }} &mdash; {{ $partner->name }}
                @else
                    {{ __('Your turn') }}
                @endif
            </p>
        @endif

        {{-- Progress indicator --}}
        <div class="flex items-center gap-2 px-6 pt-6">
            @foreach (range(1, 5) as $i)
                <div class="h-1.5 flex-1 rounded-full {{ $i <= $step ? 'bg-zinc-800 dark:bg-zinc-100' : 'bg-zinc-200 dark:bg-zinc-700' }}"></div>
            @endforeach
        </div>
        <p class="px-6 pt-2 text-xs text-neutral-400">{{ __('Step :step of 5', ['step' => $step]) }}</p>

        <div class="flex flex-1 flex-col justify-center gap-6 px-6 pb-16 pt-8">

            {{-- Step 1 — energy level --}}
            @if ($step === 1)
                <flux:heading size="xl" class="text-2xl font-bold">
                    {{ __("What's your energy tonight?") }}
                </flux:heading>
                <p class="text-sm text-neutral-500">{{ __('energy') }}</p>
                <div class="flex flex-col gap-3">
                    @foreach ([
                        ['value' => 'lively', 'label' => '🎉 Lively', 'sub' => 'Busy, loud, fun'],
                        ['value' => 'moderate', 'label' => '😊 Moderate', 'sub' => 'Relaxed but social'],
                        ['value' => 'quiet', 'label' => '🤫 Quiet', 'sub' => 'Low-key, easy conversation'],
                    ] as $opt)
                        <button
                            wire:click="answer('energy', '{{ $opt['value'] }}')"
                            class="flex flex-col rounded-2xl border border-zinc-200 p-5 text-left dark:border-zinc-700"
                        >
                            <span class="text-lg font-semibold">{{ $opt['label'] }}</span>
                            <span class="text-sm text-neutral-500">{{ $opt['sub'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endif

            {{-- Step 2 — hunger level --}}
            @if ($step === 2)
                <flux:heading size="xl" class="text-2xl font-bold">
                    {{ __('How hungry are you?') }}
                </flux:heading>
                <p class="text-sm text-neutral-500">{{ __('hunger') }}</p>
                <div class="flex flex-col gap-3">
                    @foreach ([
                        ['value' => 'light', 'label' => '🥗 Light bite', 'sub' => 'Snack or small plates'],
                        ['value' => 'moderate', 'label' => '🍝 Moderate', 'sub' => 'A solid meal'],
                        ['value' => 'hungry', 'label' => '🥩 Very hungry', 'sub' => 'Big portions, hearty food'],
                    ] as $opt)
                        <button
                            wire:click="answer('hunger', '{{ $opt['value'] }}')"
                            class="flex flex-col rounded-2xl border border-zinc-200 p-5 text-left dark:border-zinc-700"
                        >
                            <span class="text-lg font-semibold">{{ $opt['label'] }}</span>
                            <span class="text-sm text-neutral-500">{{ $opt['sub'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endif

            {{-- Step 3 — new vs familiar --}}
            @if ($step === 3)
                <flux:heading size="xl" class="text-2xl font-bold">
                    {{ __('Something new or a familiar spot?') }}
                </flux:heading>
                <p class="text-sm text-neutral-500">{{ __('familiar') }}</p>
                <div class="flex flex-col gap-3">
                    @foreach ([
                        ['value' => 'new', 'label' => '🗺️ Something new', 'sub' => 'A place you haven\'t tried lately'],
                        ['value' => 'familiar', 'label' => '🏠 A favorite', 'sub' => 'Somewhere you love and trust'],
                        ['value' => 'either', 'label' => '🤷 Either', 'sub' => 'Surprise me'],
                    ] as $opt)
                        <button
                            wire:click="answer('familiarity', '{{ $opt['value'] }}')"
                            class="flex flex-col rounded-2xl border border-zinc-200 p-5 text-left dark:border-zinc-700"
                        >
                            <span class="text-lg font-semibold">{{ $opt['label'] }}</span>
                            <span class="text-sm text-neutral-500">{{ $opt['sub'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endif

            {{-- Step 4 — max distance --}}
            @if ($step === 4)
                <flux:heading size="xl" class="text-2xl font-bold">
                    {{ __('How far are you willing to go?') }}
                </flux:heading>
                <p class="text-sm text-neutral-500">{{ __('distance') }}</p>
                <div class="flex flex-col gap-3">
                    @foreach ([
                        ['value' => 'nearby', 'label' => '📍 Nearby', 'sub' => 'Under 2 miles'],
                        ['value' => 'close', 'label' => '🚗 Close', 'sub' => 'Under 5 miles'],
                        ['value' => 'anywhere', 'label' => '🌍 Anywhere', 'sub' => 'Distance doesn\'t matter'],
                    ] as $opt)
                        <button
                            wire:click="answer('distance', '{{ $opt['value'] }}')"
                            class="flex flex-col rounded-2xl border border-zinc-200 p-5 text-left dark:border-zinc-700"
                        >
                            <span class="text-lg font-semibold">{{ $opt['label'] }}</span>
                            <span class="text-sm text-neutral-500">{{ $opt['sub'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endif

            {{-- Step 5 — cuisine preference --}}
            @if ($step === 5)
                <flux:heading size="xl" class="text-2xl font-bold">
                    {{ __('Any cuisine in mind?') }}
                </flux:heading>
                <p class="text-sm text-neutral-500">{{ __('cuisine') }}</p>
                <div class="flex flex-col gap-3">
                    <button
                        wire:click="answer('cuisine', null)"
                        class="flex flex-col rounded-2xl border border-zinc-200 p-5 text-left dark:border-zinc-700"
                    >
                        <span class="text-lg font-semibold">🎲 {{ __('Surprise me') }}</span>
                        <span class="text-sm text-neutral-500">{{ __('Pick the best match regardless of cuisine') }}</span>
                    </button>
                    @foreach (['Italian', 'Mexican', 'American', 'Thai', 'Japanese', 'Indian', 'Chinese', 'Mediterranean'] as $cuisine)
                        <button
                            wire:click="answer('cuisine', '{{ $cuisine }}')"
                            class="rounded-2xl border border-zinc-200 p-5 text-left text-lg font-semibold dark:border-zinc-700"
                        >
                            {{ $cuisine }}
                        </button>
                    @endforeach
                </div>
            @endif

        </div>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- RESULT — single-card pattern matching Quick Pick                  --}}
    {{-- ---------------------------------------------------------------- --}}
    @if ($state === 'result' && $this->restaurant)
        <div class="flex flex-1 flex-col">
            <div class="flex flex-1 flex-col justify-center gap-4 px-6 pt-8">
                <flux:badge size="sm" class="self-start">
                    {{ __('Quiz Pick') }}
                </flux:badge>

                <flux:heading size="xl" class="text-3xl font-bold leading-tight">
                    {{ $this->restaurant->name }}
                </flux:heading>

                @if (! empty($this->restaurant->cuisine_tags))
                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->restaurant->cuisine_tags as $tag)
                            <flux:badge>{{ $tag }}</flux:badge>
                        @endforeach
                    </div>
                @endif

                <div class="flex items-center gap-3 text-sm text-neutral-500 dark:text-neutral-400">
                    @if ($this->restaurant->price_level !== null)
                        <span>{{ str_repeat('$', $this->restaurant->price_level) }}</span>
                    @endif
                    @if ($this->restaurant->address)
                        <span aria-hidden="true">·</span>
                        <span>{{ $this->restaurant->address }}</span>
                    @endif
                </div>
            </div>

            <div class="flex flex-col gap-3 px-6 pb-12 pt-6">
                <flux:button
                    variant="primary"
                    class="w-full py-4 text-base font-semibold"
                    wire:click="going"
                >
                    {{ __('Going ✓') }}
                </flux:button>

                <flux:button
                    class="w-full py-4 text-base"
                    wire:click="reject"
                >
                    {{ __('Not this one') }}
                </flux:button>
            </div>
        </div>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- EMPTY                                                             --}}
    {{-- ---------------------------------------------------------------- --}}
    @if ($state === 'empty')
        <div class="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
            <flux:heading size="xl">{{ __('No matches found') }}</flux:heading>
            <flux:text class="text-neutral-500 dark:text-neutral-400">
                {{ __("We couldn't find a match for your answers. Try loosening your preferences.") }}
            </flux:text>
            <flux:button wire:click="restart" variant="primary">
                {{ __('Start over') }}
            </flux:button>
        </div>
    @endif

</div>
