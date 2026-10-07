<?php

use App\Actions\ParseVibe;
use App\Concerns\ComputesRestaurantPresentation;
use App\Enums\ModeUsed;
use App\Enums\PrimaryCuisine;
use App\Models\HouseholdState;
use App\Models\Restaurant;
use App\Models\Visit;
use App\Services\QuizAnswers;
use App\Services\QuizService;
use App\Services\WeatherService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Vibe Check')] class extends Component {
    use ComputesRestaurantPresentation;

    private const PARSED_FIELDS = ['energy', 'hunger', 'familiarity', 'cuisine', 'serviceLevel', 'dineInTakeout'];

    private const HARD_FILTER_FIELDS = ['serviceLevel', 'dineInTakeout', 'cuisine'];

    /** Current state: 'input' | 'clarify' | 'result' | 'empty' */
    public string $state = 'input';

    public string $vibe = '';

    /** @var array<int, string> */
    public array $chips = [];

    public ?float $lat = null;

    public ?float $lng = null;

    public ?string $energy = null;
    public ?string $hunger = null;
    public ?string $familiarity = null;
    public ?string $cuisine = null;
    public ?string $serviceLevel = null;
    public ?string $dineInTakeout = null;

    /** Combined text sent to Jev; null when Jev was not used. */
    #[Locked]
    public ?string $jevVibe = null;

    /** @var array<int, string> Hard-filter fields Jev was unsure about. */
    #[Locked]
    public array $unsureFields = [];

    /** @var array<int, string> Clarifier fields still to ask (max 2). */
    #[Locked]
    public array $pendingClarifiers = [];

    #[Locked]
    public int $clarifierTotal = 0;

    private const CLARIFIER_OPTIONS = [
        'serviceLevel' => ['quick_easy', 'casual_sit_down', 'nicer_night_out', 'special_occasion'],
        'dineInTakeout' => ['dine_in', 'takeout', 'either'],
    ];

    /** @var list<array{id: int, name: string, percent: int}> */
    #[Locked]
    public array $ranking = [];

    #[Locked]
    public int $heroIndex = 0;

    /** @var array<int, string> Hard filters neutralized from the empty state. */
    #[Locked]
    public array $loosenedFields = [];

    public string $tagline = '';

    public ?string $distanceLabel = null;

    public function usesJev(): bool
    {
        return $this->jevVibe !== null;
    }

    private function resetParsed(): void
    {
        foreach (self::PARSED_FIELDS as $field) {
            $this->{$field} = null;
        }
        $this->jevVibe = null;
        $this->unsureFields = [];
        $this->pendingClarifiers = [];
        $this->ranking = [];
        $this->heroIndex = 0;
        $this->loosenedFields = [];
    }

    public function submit(): void
    {
        $this->resetParsed();

        $this->vibe = trim($this->vibe);

        $allowedChips = collect(config('vibes'))->flatten()->all();

        $this->validate([
            'vibe' => ['nullable', 'string', 'max:280'],
            'chips' => ['array'],
            'chips.*' => ['string', Rule::in($allowedChips)],
        ]);

        if ($this->vibe === '' && $this->chips === []) {
            $this->addError('vibe', __('Describe your vibe or pick at least one tag.'));

            return;
        }

        $combined = collect([$this->vibe])
            ->merge(array_map(fn (string $chip): string => str_replace('_', ' ', $chip), $this->chips))
            ->filter(fn (string $part): bool => $part !== '')
            ->implode(', ');

        $key = 'vibe:'.Auth::id();

        if (! RateLimiter::tooManyAttempts($key, 20)) {
            RateLimiter::hit($key, 3600);
            $parsed = app(ParseVibe::class)->execute($combined);

            if ($parsed !== null) {
                $this->jevVibe = $combined;
                foreach ($parsed as $field => $value) {
                    $this->{$field} = $value;
                }
                $this->unsureFields = array_values(array_filter(
                    self::HARD_FILTER_FIELDS,
                    fn (string $field): bool => ! array_key_exists($field, $parsed),
                ));
            }
        }

        $this->proceed();
    }

    private function proceed(): void
    {
        $this->pendingClarifiers = array_slice($this->unsureFields, 0, 2);
        $this->clarifierTotal = count($this->pendingClarifiers);

        if ($this->pendingClarifiers === []) {
            $this->resolve();

            return;
        }

        $this->state = 'clarify';
    }

    public function answer(string $field, mixed $value): void
    {
        if (! in_array($field, $this->pendingClarifiers, true)) {
            return;
        }

        $allowed = $field === 'cuisine'
            ? $value === null || (is_string($value) && PrimaryCuisine::tryFrom($value) !== null)
            : is_string($value) && in_array($value, self::CLARIFIER_OPTIONS[$field], true);

        if (! $allowed) {
            return;
        }

        $this->{$field} = $value;
        $this->dropClarifier($field);
    }

    public function skipClarifier(): void
    {
        if ($this->pendingClarifiers !== []) {
            $this->dropClarifier($this->pendingClarifiers[0]);
        }
    }

    private function dropClarifier(string $field): void
    {
        $this->pendingClarifiers = array_values(array_diff($this->pendingClarifiers, [$field]));

        if ($this->pendingClarifiers === []) {
            $this->resolve();
        }
    }

    private function resolve(): void
    {
        $weather = ($this->lat !== null && $this->lng !== null)
            ? app(WeatherService::class)->fetch($this->lat, $this->lng)
            : null;

        $ranked = app(QuizService::class)->ranked(Auth::user(), $this->buildAnswers(), $weather, $this->jevVibe);

        $top = $ranked->max('score');
        $spread = $top - $ranked->min('score');
        $this->ranking = $ranked->map(fn (array $row): array => [
            'id' => $row['restaurant']->id,
            'name' => $row['restaurant']->name,
            'percent' => $spread > 0 ? 10 + (int) round(($row['score'] - ($top - $spread)) / $spread * 90) : 100,
        ])->all();
        $this->heroIndex = 0;

        $this->showHeroOrEmpty();
    }

    private function showHeroOrEmpty(): void
    {
        $hero = $this->hero;

        if ($hero === null) {
            $this->state = 'empty';

            return;
        }

        $this->state = 'result';
        $this->tagline = $this->resolveTagline($hero);
        $this->distanceLabel = $this->resolveDistanceLabel($hero);
    }

    #[Computed]
    public function hero(): ?Restaurant
    {
        $id = $this->ranking[$this->heroIndex]['id'] ?? null;

        return $id === null ? null : Restaurant::find($id);
    }

    public function reject(): void
    {
        $this->heroIndex++;
        unset($this->hero);
        $this->showHeroOrEmpty();
    }

    public function going(): void
    {
        $restaurant = Restaurant::findOrFail($this->ranking[$this->heroIndex]['id']);

        Visit::create([
            'user_id' => Auth::id(),
            'restaurant_id' => $restaurant->id,
            'visited_at' => now(),
            'mode_used' => ModeUsed::Vibe,
        ]);

        $restaurant->increment('visit_count');
        $restaurant->update(['last_visited_at' => now()]);
        HouseholdState::recordPick(Auth::user());

        Flux::toast(variant: 'success', text: __('Enjoy your meal! 🍽️'));

        $this->redirect(route('dashboard'), navigate: true);
    }

    /**
     * Up to 3 [field => count] hard filters worth loosening, most restrictive first.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function emptyStateFilters(): array
    {
        $counts = app(QuizService::class)->filterExclusionCounts(Auth::user(), $this->buildAnswers());

        return collect($counts)
            ->filter(fn (int $count, string $field): bool => $count > 0
                && in_array($field, self::HARD_FILTER_FIELDS, true)
                && ! in_array($field, $this->loosenedFields, true))
            ->sortDesc()
            ->take(3)
            ->all();
    }

    public function loosenFilter(string $field): void
    {
        if (! array_key_exists($field, $this->emptyStateFilters)) {
            return;
        }

        $this->loosenedFields[] = $field;
        unset($this->emptyStateFilters);
        $this->resolve();
    }

    public function tryAnotherVibe(): void
    {
        $this->resetParsed();
        $this->state = 'input';
    }

    public function filterFieldLabel(string $field): string
    {
        return match ($field) {
            'dineInTakeout' => __('dine-in/takeout preference'),
            'serviceLevel' => __('service level'),
            'cuisine' => __('cuisine'),
            default => $field,
        };
    }

    /** Soft fields use QuizAnswers defaults; unanswered or loosened hard filters are neutralized. */
    private function buildAnswers(): QuizAnswers
    {
        $service = app(QuizService::class);
        $answers = new QuizAnswers(
            energy: $this->energy ?? 'moderate',
            hunger: $this->hunger ?? 'full_meal',
            familiarity: $this->familiarity ?? 'either',
            distance: 'anywhere',
            cuisine: $this->cuisine,
            lat: $this->lat,
            lng: $this->lng,
            serviceLevel: $this->serviceLevel ?? 'casual_sit_down',
            dineInTakeout: $this->dineInTakeout ?? 'either',
        );

        foreach (self::HARD_FILTER_FIELDS as $field) {
            if ($this->{$field} === null || in_array($field, $this->loosenedFields, true)) {
                $answers = $service->neutralize($answers, $field);
            }
        }

        return $answers;
    }
}; ?>

<div
    class="flex min-h-[calc(100dvh-4rem)] flex-col gap-4 p-4"
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
    @if ($state === 'input')
        <form wire:submit="submit" class="flex flex-col gap-4">
            <flux:heading size="lg" class="font-display uppercase">{{ __('Vibe Check') }}</flux:heading>

            <flux:textarea wire:model="vibe" rows="3" maxlength="280" :placeholder="__('Cozy date night, something new...')" />
            <flux:error name="vibe" />
            <flux:text class="text-xs">{{ __('Sent to our AI provider to match your vibe.') }}</flux:text>

            <livewire:vibe-picker wire:model="chips" />

            <flux:button type="submit" variant="primary" class="w-full">{{ __('Find my place') }}</flux:button>
        </form>
    @elseif ($state === 'clarify')
        <div class="flex flex-col gap-4">
            <flux:text class="text-xs">{{ __('Step :current of :total', ['current' => $clarifierTotal - count($pendingClarifiers) + 1, 'total' => $clarifierTotal]) }}</flux:text>
            <x-dynamic-component :component="'quiz.steps.' . $pendingClarifiers[0]" />
            <flux:button wire:click="skipClarifier" variant="ghost" class="w-full">{{ __('Skip') }}</flux:button>
        </div>
    @elseif ($state === 'result' && $this->hero)
        <x-restaurant-result-ticket
            :restaurant="$this->hero"
            :badge-label="__('Vibe Check')"
            :tagline="$tagline"
            :distance-label="$distanceLabel"
        />

        @unless ($this->usesJev())
            <flux:text class="text-sm text-neutral-400 dark:text-neutral-500">
                {{ __('AI matching unavailable — ranked from your usual preferences.') }}
            </flux:text>
        @endunless

        <div class="flex flex-col gap-3">
            <flux:button variant="primary" class="w-full py-4 text-base font-semibold" wire:click="going">{{ __('Going ✓') }}</flux:button>
            <flux:button class="w-full py-4 text-base" wire:click="reject">{{ __('Not this one') }}</flux:button>
        </div>

        <details wire:ignore.self class="text-sm">
            <summary class="cursor-pointer text-neutral-500">{{ __('See how they scored') }}</summary>
            <ul class="mt-2 flex flex-col gap-2">
                @foreach ($ranking as $i => $row)
                    <li class="{{ $i === $heroIndex ? 'font-semibold' : '' }}">
                        <div class="flex justify-between"><span>{{ $row['name'] }}</span><span>{{ $row['percent'] }}%</span></div>
                        <div class="h-1.5 rounded-full bg-zinc-200 dark:bg-zinc-700">
                            <div class="h-1.5 rounded-full {{ $i === $heroIndex ? 'bg-zinc-800 dark:bg-zinc-100' : 'bg-zinc-400' }}" style="width: {{ $row['percent'] }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </details>
    @elseif ($state === 'empty')
        <div class="flex flex-1 flex-col items-center justify-center gap-4 text-center">
            <flux:heading size="xl">{{ __('No matches found') }}</flux:heading>

            <div class="flex w-full max-w-xs flex-col gap-3">
                @foreach ($this->emptyStateFilters as $field => $count)
                    <flux:button wire:click="loosenFilter('{{ $field }}')">
                        {{ __('Loosen :filter', ['filter' => $this->filterFieldLabel($field)]) }}
                    </flux:button>
                @endforeach
            </div>

            <flux:button wire:click="tryAnotherVibe" variant="primary">{{ __('Try another vibe') }}</flux:button>
        </div>
    @endif
</div>
