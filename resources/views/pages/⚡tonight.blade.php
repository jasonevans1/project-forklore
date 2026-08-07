<?php

use App\Enums\ModeUsed;
use App\Models\Restaurant;
use App\Models\Visit;
use App\Services\TonightService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Something Happening Tonight')] class extends Component {
    /** Current UI state: 'idle' | 'result' | 'empty' */
    public string $state = 'idle';

    /** ID of the currently displayed restaurant. */
    public ?int $restaurantId = null;

    /** Event detail label, e.g. "Trivia starts at 7pm". */
    public string $eventLabel = '';

    /** Restaurant IDs the user has rejected this session. */
    public array $excludedIds = [];

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
     * Find a restaurant with an event happening in the next 3 hours.
     */
    public function findTonightsPick(): void
    {
        $service = app(TonightService::class);

        $restaurant = $service->pick(Auth::user(), $this->excludedIds);

        if ($restaurant === null) {
            $this->state = 'empty';
            $this->restaurantId = null;

            return;
        }

        $this->restaurantId = $restaurant->id;
        $this->state = 'result';
        $this->eventLabel = $service->eventLabel($restaurant);
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
            'mode_used' => ModeUsed::Tonight,
        ]);

        $restaurant->increment('visit_count');
        $restaurant->update(['last_visited_at' => now()]);

        Flux::toast(variant: 'success', text: __('Have a great time tonight! 🎉'));

        $this->redirect(route('dashboard'), navigate: true);
    }

    /**
     * Reject the current pick and immediately find the next one, excluding it for the session.
     */
    public function reject(): void
    {
        if ($this->restaurantId !== null) {
            $this->excludedIds[] = $this->restaurantId;
            $this->restaurantId = null;
        }

        $this->findTonightsPick();
    }
}; ?>

<div class="flex min-h-[calc(100dvh-4rem)] flex-col">

    {{-- ---------------------------------------------------------------- --}}
    {{-- IDLE — prompt the user to discover tonight's events               --}}
    {{-- ---------------------------------------------------------------- --}}
    @if ($state === 'idle')
        <div class="flex flex-1 flex-col items-center justify-end gap-4 px-6 pb-16">
            <flux:heading size="xl" class="text-center text-2xl font-bold">
                {{ __('See what\'s happening tonight') }}
            </flux:heading>
            <flux:text class="mb-4 text-center text-neutral-500 dark:text-neutral-400">
                {{ __('Trivia, live music, bingo — we\'ll find a spot with something going on.') }}
            </flux:text>

            <flux:button
                variant="primary"
                class="h-16 w-full max-w-sm text-xl font-semibold"
                wire:click="findTonightsPick"
            >
                <span wire:loading.remove wire:target="findTonightsPick">{{ __("What's happening") }}</span>
                <span wire:loading wire:target="findTonightsPick">{{ __('Looking…') }}</span>
            </flux:button>
        </div>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- RESULT — event detail above restaurant name, swipe to skip        --}}
    {{-- ---------------------------------------------------------------- --}}
    @if ($state === 'result' && $this->restaurant)
        <div
            x-data="{
                startX: 0,
                currentX: 0,
                dragging: false,
                get offset() { return this.dragging ? this.currentX - this.startX : 0 },
                get opacity() { return Math.max(0, 1 - Math.abs(this.offset) / 200) },
                onTouchStart(e) {
                    this.startX   = e.touches[0].clientX;
                    this.currentX = this.startX;
                    this.dragging = true;
                },
                onTouchMove(e) {
                    if (!this.dragging) return;
                    this.currentX = e.touches[0].clientX;
                },
                onTouchEnd() {
                    if (this.dragging && this.startX - this.currentX > 80) {
                        $wire.reject();
                    }
                    this.dragging = false;
                    this.startX   = 0;
                    this.currentX = 0;
                },
            }"
            class="flex flex-1 flex-col"
            :style="`transform: translateX(${offset}px); opacity: ${opacity}`"
            @touchstart="onTouchStart($event)"
            @touchmove.passive="onTouchMove($event)"
            @touchend="onTouchEnd()"
        >
            {{-- Card body --}}
            <div class="flex flex-1 flex-col justify-center px-6 pt-8">
                <x-restaurant-result-ticket
                    :restaurant="$this->restaurant"
                    :badge-label="__('Tonight')"
                    :event-label="$eventLabel"
                />
            </div>

            {{-- Action buttons in the thumb zone --}}
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

            {{-- Swipe hint --}}
            <p class="pb-6 text-center text-xs text-neutral-400 dark:text-neutral-600">
                {{ __('← Swipe left to skip') }}
            </p>
        </div>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- EMPTY — nothing happening tonight                                 --}}
    {{-- ---------------------------------------------------------------- --}}
    @if ($state === 'empty')
        <div class="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
            <flux:heading size="xl">{{ __('Nothing happening soon') }}</flux:heading>
            <flux:text class="text-neutral-500 dark:text-neutral-400">
                {{ __('None of your favorites have events in the next 3 hours. Add an event to a restaurant to see it here.') }}
            </flux:text>
            <flux:button as="link" href="{{ route('restaurants.index') }}" wire:navigate variant="primary">
                {{ __('View restaurants') }}
            </flux:button>
        </div>
    @endif

</div>
