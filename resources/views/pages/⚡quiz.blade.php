<?php

use App\Concerns\ComputesRestaurantPresentation;
use App\Enums\ModeUsed;
use App\Enums\QuizQuestion;
use App\Models\HouseholdState;
use App\Models\Restaurant;
use App\Models\Visit;
use App\Services\QuizAnswers;
use App\Services\QuizService;
use App\Services\WeatherData;
use App\Services\WeatherService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Guided Quiz')] class extends Component {
    use ComputesRestaurantPresentation;

    /** Current state: 'questions' | 'result' | 'empty' */
    public string $state = 'questions';

    /** Whether the intro screen has been dismissed for this session. */
    public bool $introDismissed = false;

    /** Current wizard step (1–7). */
    public int $step = 1;

    /** ID of the top-matched restaurant. */
    public ?int $restaurantId = null;

    /** Filter field names already tried via loosenFilter() this session. */
    public array $triedFilterLoosens = [];

    /** The filter field currently neutralized on the displayed result, if any. */
    public ?string $activeLoosenedField = null;

    /** Weather-aware tagline shown on the result card. */
    public string $tagline = '';

    /** Formatted distance label, e.g. "1.4 mi". Null when location is unavailable. */
    public ?string $distanceLabel = null;

    /** Name of the runner-up revealed via peekRunnerUp(), or a no-match indicator. Null until peeked. */
    public ?string $peekedRunnerUpName = null;

    // Quiz answer fields — populated one per step.
    public ?string $dineInTakeout = null;
    public ?string $serviceLevel = null;
    public ?string $energy = null;
    public ?string $hunger = null;
    public ?string $familiarity = null;
    public ?string $distance = null;
    public ?string $cuisine = null;

    /** User latitude — set by Alpine via Geolocation API. */
    public ?float $lat = null;

    /** User longitude — set by Alpine via Geolocation API. */
    public ?float $lng = null;

    /** Session key under which wizard progress is persisted. */
    private const SESSION_KEY = 'quiz.wizard';

    public function mount(): void
    {
        $snapshot = session(self::SESSION_KEY);

        if ($snapshot === null) {
            return;
        }

        $this->step = $snapshot['step'];
        $this->state = $snapshot['state'];
        $this->restaurantId = $snapshot['restaurantId'];
        $this->dineInTakeout = $snapshot['dineInTakeout'];
        $this->serviceLevel = $snapshot['serviceLevel'];
        $this->energy = $snapshot['energy'];
        $this->hunger = $snapshot['hunger'];
        $this->familiarity = $snapshot['familiarity'];
        $this->distance = $snapshot['distance'];
        $this->cuisine = $snapshot['cuisine'];
        $this->triedFilterLoosens = $snapshot['triedFilterLoosens'] ?? [];
        $this->activeLoosenedField = $snapshot['activeLoosenedField'] ?? null;
        $this->introDismissed = $snapshot['introDismissed'] ?? true;
    }

    // -------------------------------------------------------------------------
    // Computed
    // -------------------------------------------------------------------------

    #[Computed]
    public function restaurant(): ?Restaurant
    {
        return $this->restaurantId ? Restaurant::find($this->restaurantId) : null;
    }

    /**
     * Up to 3 [field => count] entries from filterExclusionCounts(), sorted
     * descending by count, excluding zero-count and already-tried fields.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function emptyStateFilters(): array
    {
        $counts = app(QuizService::class)->filterExclusionCounts(Auth::user(), $this->buildAnswers());

        return collect($counts)
            ->filter(fn (int $count, string $field): bool => $count > 0 && ! in_array($field, $this->triedFilterLoosens, true))
            ->sortDesc()
            ->take(3)
            ->all();
    }

    /**
     * Message noting how many steps were skipped due to a quick_easy service
     * level, or null when nothing was skipped. Shown on the result card.
     */
    #[Computed]
    public function skippedStepsMessage(): ?string
    {
        $skipped = $this->skippedStepFields();

        if ($skipped === []) {
            return null;
        }

        return trans_choice(
            'You picked fast food, so we skipped :count question|You picked fast food, so we skipped :count questions',
            count($skipped),
            ['count' => count($skipped)],
        );
    }

    /**
     * Friendly label for one of the 4 hard-filter field names.
     */
    public function filterFieldLabel(string $field): string
    {
        return match ($field) {
            'dineInTakeout' => __('dine-in/takeout preference'),
            'serviceLevel' => __('service level'),
            'cuisine' => __('cuisine'),
            'distance' => __('distance'),
            default => $field,
        };
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    /**
     * Dismiss the intro screen and enter the wizard at step 1.
     */
    public function startQuiz(): void
    {
        $this->introDismissed = true;

        $this->persistSession();
    }

    /**
     * Store the answer for the current step and advance — resolving the result
     * once no non-skipped steps remain.
     */
    public function answer(string $field, mixed $value): void
    {
        $this->{$field} = $value;

        $next = $this->nextRawStep($this->step);

        if ($next === null) {
            $this->resolve();
        } else {
            $this->step = $next;
        }

        $this->persistSession();
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
            $this->answersForCurrentResult(),
            $winner,
            $this->resolveWeather(),
        );

        if ($runnerUp === null) {
            $this->state = 'empty';
            $this->restaurantId = null;

            return;
        }

        $this->restaurantId = $runnerUp->id;
        $this->tagline = $this->resolveTagline($runnerUp);
        $this->distanceLabel = $this->resolveDistanceLabel($runnerUp);
        $this->peekedRunnerUpName = null;
        unset($this->restaurant);
    }

    /**
     * Reveal the runner-up's name for the currently displayed result without
     * changing the displayed restaurant.
     */
    public function peekRunnerUp(): void
    {
        $current = $this->restaurant;

        if ($current === null) {
            return;
        }

        $runnerUp = app(QuizService::class)->runnerUp(
            Auth::user(),
            $this->answersForCurrentResult(),
            $current,
            $this->resolveWeather(),
        );

        $this->peekedRunnerUpName = $runnerUp?->name ?? __('No other match found');
    }

    /**
     * Recompute the pool with one hard filter relaxed for this attempt only.
     * Stays in the empty state (tracking the tried field) if still no match.
     */
    public function loosenFilter(string $field): void
    {
        $service = app(QuizService::class);
        $neutralized = $service->neutralize($this->buildAnswers(), $field);
        $restaurant = $service->topMatch(Auth::user(), $neutralized, $this->resolveWeather());

        if ($restaurant === null) {
            $this->triedFilterLoosens[] = $field;
            $this->persistSession();

            return;
        }

        $this->restaurantId = $restaurant->id;
        $this->activeLoosenedField = $field;
        $this->state = 'result';
        $this->tagline = $this->resolveTagline($restaurant);
        $this->distanceLabel = $this->resolveDistanceLabel($restaurant);
        $this->peekedRunnerUpName = null;
        unset($this->restaurant);
        $this->logQuizCompletion();
        $this->persistSession();
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

        $this->clearSession();

        $this->redirect(route('dashboard'), navigate: true);
    }

    /**
     * Return to the previous non-skipped step. No-op if already on the
     * first effective step.
     */
    public function back(): void
    {
        $previous = $this->previousRawStep($this->step);

        if ($previous === null) {
            return;
        }

        $this->step = $previous;

        $this->persistSession();
    }

    /**
     * Reset wizard back to step 1.
     */
    public function restart(): void
    {
        $this->state = 'questions';
        $this->step = 1;
        $this->restaurantId = null;
        $this->dineInTakeout = null;
        $this->serviceLevel = null;
        $this->energy = null;
        $this->hunger = null;
        $this->familiarity = null;
        $this->distance = null;
        $this->cuisine = null;
        $this->triedFilterLoosens = [];
        $this->activeLoosenedField = null;
        $this->peekedRunnerUpName = null;
        $this->introDismissed = true;

        $this->clearSession();
    }

    // -------------------------------------------------------------------------
    // Step registry / effective-count helpers
    // -------------------------------------------------------------------------

    /**
     * The current step's field name.
     */
    public function currentField(): ?string
    {
        return $this->steps()[$this->step]->value;
    }

    /**
     * Whether there is a previous non-skipped step to go back to.
     */
    public function canGoBack(): bool
    {
        return $this->previousRawStep($this->step) !== null;
    }

    /**
     * Translate a raw step number into its effective "Step X of Y" count —
     * the number of non-skipped slots at or before the given raw step.
     */
    public function effectiveStepNumber(int $rawStep): int
    {
        $answers = $this->buildAnswers();

        return collect($this->steps())
            ->filter(fn (QuizQuestion $slot, int $key): bool => ! $slot->shouldSkip($answers) && $key <= $rawStep)
            ->count();
    }

    /**
     * Total number of non-skipped slots in the registry.
     */
    public function effectiveStepTotal(): int
    {
        $answers = $this->buildAnswers();

        return collect($this->steps())
            ->filter(fn (QuizQuestion $slot): bool => ! $slot->shouldSkip($answers))
            ->count();
    }

    /**
     * The next non-skipped raw step after the given one, or null if none
     * remain (signals the quiz should resolve now).
     */
    private function nextRawStep(int $from): ?int
    {
        $answers = $this->buildAnswers();

        return collect($this->steps())
            ->filter(fn (QuizQuestion $slot, int $key): bool => $key > $from && ! $slot->shouldSkip($answers))
            ->keys()
            ->first();
    }

    /**
     * The previous non-skipped raw step before the given one, or null if
     * $from is already the first non-skipped slot.
     */
    private function previousRawStep(int $from): ?int
    {
        $answers = $this->buildAnswers();

        return collect($this->steps())
            ->filter(fn (QuizQuestion $slot, int $key): bool => $key < $from && ! $slot->shouldSkip($answers))
            ->keys()
            ->last();
    }

    /**
     * Field-name values of every step in the registry that is skipped for
     * the current answers (e.g. energy/familiarity under quick_easy).
     *
     * @return array<int, string>
     */
    private function skippedStepFields(): array
    {
        $answers = $this->buildAnswers();

        return collect($this->steps())
            ->filter(fn (QuizQuestion $slot): bool => $slot->shouldSkip($answers))
            ->map(fn (QuizQuestion $slot): string => $slot->value)
            ->values()
            ->all();
    }

    /**
     * Fixed 7-slot step registry — every slot is a real question.
     *
     * @return array<int, QuizQuestion>
     */
    private function steps(): array
    {
        return [
            1 => QuizQuestion::DineInTakeout,
            2 => QuizQuestion::ServiceLevel,
            3 => QuizQuestion::Cuisine,
            4 => QuizQuestion::Energy,
            5 => QuizQuestion::Hunger,
            6 => QuizQuestion::Distance,
            7 => QuizQuestion::Familiarity,
        ];
    }

    // -------------------------------------------------------------------------
    // Session persistence
    // -------------------------------------------------------------------------

    private function persistSession(): void
    {
        session([self::SESSION_KEY => [
            'step' => $this->step,
            'state' => $this->state,
            'restaurantId' => $this->restaurantId,
            'dineInTakeout' => $this->dineInTakeout,
            'serviceLevel' => $this->serviceLevel,
            'energy' => $this->energy,
            'hunger' => $this->hunger,
            'familiarity' => $this->familiarity,
            'distance' => $this->distance,
            'cuisine' => $this->cuisine,
            'triedFilterLoosens' => $this->triedFilterLoosens,
            'activeLoosenedField' => $this->activeLoosenedField,
            'introDismissed' => $this->introDismissed,
        ]]);
    }

    private function clearSession(): void
    {
        session()->forget(self::SESSION_KEY);
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
        $this->activeLoosenedField = null;
        $this->state = 'result';
        $this->tagline = $this->resolveTagline($restaurant);
        $this->distanceLabel = $this->resolveDistanceLabel($restaurant);
        $this->logQuizCompletion();
    }

    /**
     * Log which steps were skipped (and why) for this completed run.
     */
    private function logQuizCompletion(): void
    {
        $skipped = $this->skippedStepFields();

        Log::info('Quiz completed', [
            'user_id' => Auth::id(),
            'skipped_steps' => $skipped,
            'skip_reason' => $skipped === [] ? null : 'quick_easy_service_level',
        ]);
    }

    /**
     * The answers behind whatever result is currently on screen — neutralized
     * by activeLoosenedField when the current result came from loosenFilter().
     */
    private function answersForCurrentResult(): QuizAnswers
    {
        $answers = $this->buildAnswers();

        if ($this->activeLoosenedField === null) {
            return $answers;
        }

        return app(QuizService::class)->neutralize($answers, $this->activeLoosenedField);
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
            serviceLevel: $this->serviceLevel ?? 'casual_sit_down',
            dineInTakeout: $this->dineInTakeout ?? 'either',
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
    {{-- QUESTIONS — skip-aware 7-step wizard                              --}}
    {{-- ---------------------------------------------------------------- --}}
    @if ($state === 'questions')

        @if (! $introDismissed)
            {{-- Intro screen --}}
            <div class="flex flex-1 flex-col justify-center gap-4 px-6 text-center">
                <flux:heading size="xl">{{ __('Guided Quiz') }}</flux:heading>
                <flux:text class="text-neutral-500 dark:text-neutral-400">
                    {{ __('Answer 5-7 quick questions and we\'ll pick the one restaurant that fits tonight.') }}
                </flux:text>
            </div>

            <div class="px-6 pb-12 pt-6">
                <flux:button
                    variant="primary"
                    class="w-full py-4 text-base font-semibold"
                    wire:click="startQuiz"
                >
                    {{ __('Start quiz') }}
                </flux:button>
            </div>
        @else
            {{-- Header: turn indicator + start-over action --}}
            @php $partner = Auth::user()->partner; @endphp
            <div class="flex items-center justify-between px-6 pt-4">
                @if ($partner)
                    <p class="text-sm text-neutral-400 dark:text-neutral-500">
                        @if (HouseholdState::isPartnersTurn(Auth::user()))
                            {{ __('Partner\'s turn') }} &mdash; {{ $partner->name }}
                        @else
                            {{ __('Your turn') }}
                        @endif
                    </p>
                @else
                    <span></span>
                @endif

                <flux:button size="sm" variant="ghost" wire:click="restart">
                    {{ __('Start over') }}
                </flux:button>
            </div>

            {{-- Progress indicator --}}
            <div class="flex items-center gap-2 px-6 pt-6">
                @foreach (range(1, $this->effectiveStepTotal()) as $i)
                    <div class="h-1.5 flex-1 rounded-full {{ $i <= $this->effectiveStepNumber($step) ? 'bg-zinc-800 dark:bg-zinc-100' : 'bg-zinc-200 dark:bg-zinc-700' }}"></div>
                @endforeach
            </div>
            <p class="px-6 pt-2 text-xs text-neutral-400">{{ __('Step :step of :total', ['step' => $this->effectiveStepNumber($step), 'total' => $this->effectiveStepTotal()]) }}</p>

            <div class="flex flex-1 flex-col justify-center gap-6 px-6 pb-16 pt-8">
                @if ($this->currentField())
                    <x-dynamic-component :component="'quiz.steps.' . $this->currentField()" />
                @endif
            </div>

            @if ($this->canGoBack())
                <div class="px-6 pb-12 pt-6">
                    <flux:button
                        class="w-full py-4 text-base"
                        wire:click="back"
                    >
                        {{ __('Back') }}
                    </flux:button>
                </div>
            @endif
        @endif
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

                    @if ($distanceLabel !== null)
                        @if ($this->restaurant->price_level !== null)
                            <span aria-hidden="true">·</span>
                        @endif
                        <span>{{ $distanceLabel }}</span>
                    @endif
                </div>

                @if ($tagline)
                    <flux:text class="text-base italic text-neutral-600 dark:text-neutral-300">
                        {{ $tagline }}
                    </flux:text>
                @endif

                @if ($this->restaurant->address)
                    <flux:text class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ $this->restaurant->address }}
                    </flux:text>
                @endif

                @if ($this->skippedStepsMessage)
                    <flux:text class="text-sm text-neutral-400 dark:text-neutral-500">
                        {{ $this->skippedStepsMessage }}
                    </flux:text>
                @endif
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

                <flux:button
                    variant="ghost"
                    size="sm"
                    class="w-full"
                    wire:click="peekRunnerUp"
                >
                    {{ __('Show runner-up') }}
                </flux:button>

                @if ($peekedRunnerUpName !== null)
                    <flux:text class="text-center text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Runner-up:') }} {{ $peekedRunnerUpName }}
                    </flux:text>
                @endif
            </div>
        </div>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- EMPTY                                                             --}}
    {{-- ---------------------------------------------------------------- --}}
    @if ($state === 'empty')
        @php $filters = $this->emptyStateFilters(); @endphp
        <div class="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
            <flux:heading size="xl">{{ __('No matches found') }}</flux:heading>

            @if (! empty($filters))
                <flux:text class="text-neutral-500 dark:text-neutral-400">
                    {{ __('Your :filter preference is the most restrictive.', ['filter' => $this->filterFieldLabel(array_key_first($filters))]) }}
                </flux:text>

                <div class="flex flex-col gap-3 w-full max-w-xs">
                    @foreach ($filters as $field => $count)
                        <flux:button wire:click="loosenFilter('{{ $field }}')">
                            {{ __('Loosen :filter', ['filter' => $this->filterFieldLabel($field)]) }}
                        </flux:button>
                    @endforeach
                </div>
            @else
                <flux:text class="text-neutral-500 dark:text-neutral-400">
                    {{ __('Add more favorites or try different answers to find a match.') }}
                </flux:text>
            @endif

            <flux:button wire:click="restart" variant="primary">
                {{ __('Start over') }}
            </flux:button>
        </div>
    @endif

</div>
