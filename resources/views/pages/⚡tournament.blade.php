<?php

use App\Enums\ModeUsed;
use App\Models\Restaurant;
use App\Models\Visit;
use App\Services\TournamentService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tournament')] class extends Component {
    /** 'setup' | 'bracket' | 'result' | 'empty' */
    public string $state = 'setup';

    /** Ordered list of restaurant IDs in the current round. */
    public array $bracketIds = [];

    /** Index of the current head-to-head matchup within bracketIds. */
    public int $matchupIndex = 0;

    /** Winner IDs collected so far this round. */
    public array $roundWinnerIds = [];

    /** ID of the tournament champion. */
    public ?int $winnerId = null;

    // -------------------------------------------------------------------------
    // Computed
    // -------------------------------------------------------------------------

    /**
     * All restaurants in the current round, keyed by id.
     *
     * @return array<int, Restaurant>
     */
    #[Computed]
    public function bracket(): array
    {
        if (empty($this->bracketIds)) {
            return [];
        }

        $restaurants = Restaurant::findMany($this->bracketIds)->keyBy('id');

        // Preserve bracketIds order.
        return collect($this->bracketIds)
            ->map(fn (int $id) => $restaurants->get($id))
            ->filter()
            ->values()
            ->all();
    }

    /** The left (top) card of the current matchup. */
    #[Computed]
    public function leftRestaurant(): ?Restaurant
    {
        $idx = $this->matchupIndex * 2;

        return $this->bracket[$idx] ?? null;
    }

    /** The right (bottom) card of the current matchup. */
    #[Computed]
    public function rightRestaurant(): ?Restaurant
    {
        $idx = $this->matchupIndex * 2 + 1;

        return $this->bracket[$idx] ?? null;
    }

    /** The winning restaurant shown on the result card. */
    #[Computed]
    public function winner(): ?Restaurant
    {
        return $this->winnerId ? Restaurant::find($this->winnerId) : null;
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    /**
     * Seed the bracket and enter the bracket state.
     */
    public function start(): void
    {
        $restaurants = app(TournamentService::class)->seed(Auth::user());

        if (empty($restaurants)) {
            $this->state = 'empty';

            return;
        }

        $this->bracketIds = collect($restaurants)->pluck('id')->all();
        $this->matchupIndex = 0;
        $this->roundWinnerIds = [];
        $this->state = 'bracket';

        unset($this->bracket, $this->leftRestaurant, $this->rightRestaurant);
    }

    /**
     * Record a pick for the current matchup and advance.
     */
    public function pick(int $restaurantId): void
    {
        $this->roundWinnerIds[] = $restaurantId;
        $this->matchupIndex++;

        $matchupsInRound = count($this->bracketIds) / 2;

        if ($this->matchupIndex < $matchupsInRound) {
            // More matchups remain in this round.
            unset($this->leftRestaurant, $this->rightRestaurant);

            return;
        }

        $service = app(TournamentService::class);

        // Final matchup (2 competitors): the chosen restaurant IS the champion.
        if (count($this->bracketIds) === 2) {
            $picked = collect($this->bracket)->firstWhere('id', $restaurantId);
            $champion = $service->winner($picked ? [$picked] : []);
            $this->winnerId = $champion?->id;
            $this->state = 'result';

            unset($this->winner);

            return;
        }

        // Regular round complete — advance to the next round.
        $nextRound = $service->advance(
            bracket: $this->bracket,
            winnerIds: $this->roundWinnerIds,
        );

        $this->bracketIds = collect($nextRound)->pluck('id')->all();
        $this->matchupIndex = 0;
        $this->roundWinnerIds = [];

        unset($this->bracket, $this->leftRestaurant, $this->rightRestaurant);
    }

    /**
     * Log a visit for the champion and return to the dashboard.
     */
    public function going(): void
    {
        $restaurant = Restaurant::findOrFail($this->winnerId);

        Visit::create([
            'user_id' => Auth::id(),
            'restaurant_id' => $restaurant->id,
            'visited_at' => now(),
            'mode_used' => ModeUsed::Tournament,
        ]);

        $restaurant->increment('visit_count');
        $restaurant->update(['last_visited_at' => now()]);

        Flux::toast(variant: 'success', text: __('Enjoy your meal! 🍽️'));

        $this->redirect(route('dashboard'), navigate: true);
    }

    /**
     * Reset everything back to the setup screen.
     */
    public function restart(): void
    {
        $this->state = 'setup';
        $this->bracketIds = [];
        $this->matchupIndex = 0;
        $this->roundWinnerIds = [];
        $this->winnerId = null;

        unset($this->bracket, $this->leftRestaurant, $this->rightRestaurant, $this->winner);
    }
}; ?>

<div class="flex min-h-[calc(100dvh-4rem)] flex-col">

    {{-- ---------------------------------------------------------------- --}}
    {{-- SETUP                                                             --}}
    {{-- ---------------------------------------------------------------- --}}
    @if ($state === 'setup')
        <div class="flex flex-1 flex-col items-center justify-end gap-4 px-6 pb-16">
            <flux:heading size="xl" class="text-center text-2xl font-bold">
                {{ __('Tournament') }}
            </flux:heading>
            <flux:text class="mb-4 text-center text-neutral-500 dark:text-neutral-400">
                {{ __('Your favorites go head-to-head. One winner.') }}
            </flux:text>

            <flux:button
                variant="primary"
                class="h-16 w-full max-w-sm text-xl font-semibold"
                wire:click="start"
            >
                <span wire:loading.remove wire:target="start">{{ __('Start tournament') }}</span>
                <span wire:loading wire:target="start">{{ __('Seeding…') }}</span>
            </flux:button>
        </div>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- BRACKET — head-to-head matchups                                   --}}
    {{-- ---------------------------------------------------------------- --}}
    @if ($state === 'bracket')
        @php
            $total   = count($bracketIds);
            $round   = (int) (log($total, 2));
            $matchup = $matchupIndex + 1;
            $pairs   = $total / 2;
        @endphp

        {{-- Progress header --}}
        <div class="px-6 pt-6">
            <flux:text class="text-xs text-neutral-400">
                {{ __('Round of :n · Match :m of :t', ['n' => $total, 'm' => $matchup, 't' => $pairs]) }}
            </flux:text>
            <div class="mt-2 flex gap-1">
                @foreach (range(1, $pairs) as $i)
                    <div class="h-1 flex-1 rounded-full {{ $i < $matchup ? 'bg-zinc-800 dark:bg-zinc-100' : ($i === $matchup ? 'bg-zinc-400' : 'bg-zinc-200 dark:bg-zinc-700') }}"></div>
                @endforeach
            </div>
        </div>

        {{-- Two stacked matchup cards --}}
        <div class="flex flex-1 flex-col justify-center gap-4 px-6 py-8">

            {{-- Top card --}}
            @if ($this->leftRestaurant)
                <button
                    wire:click="pick({{ $this->leftRestaurant->id }})"
                    class="flex flex-col gap-2 rounded-2xl border-2 border-zinc-200 p-6 text-left transition-colors active:border-zinc-800 dark:border-zinc-700 dark:active:border-zinc-100"
                >
                    <span class="text-xl font-bold">{{ $this->leftRestaurant->name }}</span>

                    @if (! empty($this->leftRestaurant->cuisine_tags))
                        <div class="flex flex-wrap gap-1">
                            @foreach ($this->leftRestaurant->cuisine_tags as $tag)
                                <flux:badge size="sm">{{ $tag }}</flux:badge>
                            @endforeach
                        </div>
                    @endif

                    @if ($this->leftRestaurant->price_level !== null)
                        <span class="text-sm text-neutral-500">{{ str_repeat('$', $this->leftRestaurant->price_level) }}</span>
                    @endif
                </button>
            @endif

            {{-- VS divider --}}
            <div class="text-center text-sm font-semibold text-neutral-400">VS</div>

            {{-- Bottom card --}}
            @if ($this->rightRestaurant)
                <button
                    wire:click="pick({{ $this->rightRestaurant->id }})"
                    class="flex flex-col gap-2 rounded-2xl border-2 border-zinc-200 p-6 text-left transition-colors active:border-zinc-800 dark:border-zinc-700 dark:active:border-zinc-100"
                >
                    <span class="text-xl font-bold">{{ $this->rightRestaurant->name }}</span>

                    @if (! empty($this->rightRestaurant->cuisine_tags))
                        <div class="flex flex-wrap gap-1">
                            @foreach ($this->rightRestaurant->cuisine_tags as $tag)
                                <flux:badge size="sm">{{ $tag }}</flux:badge>
                            @endforeach
                        </div>
                    @endif

                    @if ($this->rightRestaurant->price_level !== null)
                        <span class="text-sm text-neutral-500">{{ str_repeat('$', $this->rightRestaurant->price_level) }}</span>
                    @endif
                </button>
            @endif
        </div>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- RESULT — single-card champion                                     --}}
    {{-- ---------------------------------------------------------------- --}}
    @if ($state === 'result' && $this->winner)
        <div class="flex flex-1 flex-col">
            <div class="flex flex-1 flex-col justify-center px-6 pt-8">
                <x-restaurant-result-ticket
                    :restaurant="$this->winner"
                    :badge-label="__('Tournament Champion')"
                />
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
                    wire:click="restart"
                >
                    {{ __('Play again') }}
                </flux:button>
            </div>
        </div>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- EMPTY                                                             --}}
    {{-- ---------------------------------------------------------------- --}}
    @if ($state === 'empty')
        <div class="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
            <flux:heading size="xl">{{ __('Not enough favorites') }}</flux:heading>
            <flux:text class="text-neutral-500 dark:text-neutral-400">
                {{ __('You need at least 4 favorites to run a tournament. Add more restaurants to play.') }}
            </flux:text>
            <flux:button as="link" href="{{ route('restaurants.create') }}" wire:navigate variant="primary">
                {{ __('Add restaurants') }}
            </flux:button>
        </div>
    @endif

</div>
