<?php

use App\Actions\PromotePlacesToFavorite;
use App\Concerns\ComputesRestaurantPresentation;
use App\Enums\ModeUsed;
use App\Models\HouseholdState;
use App\Models\Restaurant;
use App\Models\Visit;
use App\Services\QuickPickFilters;
use App\Services\QuickPickService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Quick Pick')] class extends Component {
    use ComputesRestaurantPresentation;

    /** Current UI state: 'idle' | 'result' | 'empty' */
    public string $state = 'idle';

    /** ID of the currently displayed restaurant. */
    public ?int $restaurantId = null;

    /** Weather-aware tagline shown on the result card. */
    public string $tagline = '';

    /** Formatted distance label, e.g. "1.4 mi". Null when location is unavailable. */
    public ?string $distanceLabel = null;

    /** Restaurant IDs the user has rejected this session. */
    public array $excludedIds = [];

    /** User latitude — set by Alpine.js via the browser Geolocation API. */
    public ?float $lat = null;

    /** User longitude — set by Alpine.js via the browser Geolocation API. */
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
     * Pick a restaurant for the user using the QuickPickService.
     */
    public function pick(): void
    {
        $filters = new QuickPickFilters(
            lat: $this->lat,
            lng: $this->lng,
            excludedIds: $this->excludedIds,
        );

        $restaurant = app(QuickPickService::class)->pick(Auth::user(), $filters);

        if ($restaurant === null) {
            $this->state = 'empty';
            $this->restaurantId = null;

            return;
        }

        $this->restaurantId = $restaurant->id;
        $this->state = 'result';
        $this->tagline = $this->resolveTagline($restaurant);
        $this->distanceLabel = $this->resolveDistanceLabel($restaurant);
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
            'mode_used' => ModeUsed::QuickPick,
        ]);

        $restaurant->increment('visit_count');
        $restaurant->update(['last_visited_at' => now()]);
        HouseholdState::recordPick(Auth::user());

        Flux::toast(variant: 'success', text: __('Enjoy your meal! 🍽️'));

        $this->redirect(route('dashboard'), navigate: true);
    }

    /**
     * Promote a Places-sourced restaurant to the user's favorites and open the edit screen
     * so they can add vibe tags and notes.
     */
    public function saveAsFavorite(): void
    {
        $restaurant = Restaurant::findOrFail($this->restaurantId);

        $this->authorize('update', $restaurant);

        app(PromotePlacesToFavorite::class)->execute($restaurant, Auth::user());

        $this->redirect(route('restaurants.edit', $restaurant), navigate: true);
    }

    /**
     * Reject the current pick and immediately pick again, excluding it for the session.
     */
    public function reject(): void
    {
        if ($this->restaurantId !== null) {
            $this->excludedIds[] = $this->restaurantId;
            $this->restaurantId = null;
        }

        $this->pick();
    }
}; ?>

{{--
    Single root element required by Livewire.
    Alpine's x-init silently requests geolocation so the service can use
    weather scoring and distance calculation if the user grants permission.
--}}
<div
    class="flex min-h-[calc(100dvh-4rem)] flex-col"
    x-data
    x-init="
        if ('geolocation' in navigator) {
            navigator.geolocation.getCurrentPosition(
                pos => {
                    $wire.lat = pos.coords.latitude;
                    $wire.lng  = pos.coords.longitude;
                },
                () => {} {{-- permission denied or unavailable — degrade gracefully --}}
            )
        }
    "
>

    {{-- ---------------------------------------------------------------- --}}
    {{-- IDLE — prominent Pick button placed in the thumb zone             --}}
    {{-- ---------------------------------------------------------------- --}}
    @if ($state === 'idle')
        <div class="flex flex-1 flex-col items-center justify-end gap-4 px-6 pb-16">
            @php $partner = Auth::user()->partner; @endphp
            @if ($partner)
                <p class="text-sm text-neutral-400 dark:text-neutral-500">
                    @if (\App\Models\HouseholdState::isPartnersTurn(Auth::user()))
                        {{ __('Partner\'s turn') }} &mdash; {{ $partner->name }}
                    @else
                        {{ __('Your turn') }}
                    @endif
                </p>
            @endif
            <flux:heading size="xl" class="text-center text-2xl font-bold">
                {{ __("Can't decide where to eat?") }}
            </flux:heading>
            <flux:text class="mb-4 text-center text-neutral-500 dark:text-neutral-400">
                {{ __("We'll pick the perfect spot for you.") }}
            </flux:text>

            <flux:button
                variant="primary"
                class="h-16 w-full max-w-sm text-xl font-semibold"
                wire:click="pick"
            >
                <span wire:loading.remove wire:target="pick">{{ __('Pick for us') }}</span>
                <span wire:loading wire:target="pick">{{ __('Picking…') }}</span>
            </flux:button>
        </div>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- RESULT — full-screen card with swipe-left-to-reject              --}}
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
                    :badge-label="__('Quick Pick')"
                    :tagline="$tagline"
                    :distance-label="$distanceLabel"
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

                @if ($this->restaurant->source === \App\Enums\RestaurantSource::Places)
                    <flux:button
                        variant="filled"
                        class="w-full py-4 text-base"
                        wire:click="saveAsFavorite"
                    >
                        {{ __('Save as favorite') }}
                    </flux:button>
                @endif

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
    {{-- EMPTY — no restaurants available                                  --}}
    {{-- ---------------------------------------------------------------- --}}
    @if ($state === 'empty')
        <div class="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
            <flux:heading size="xl">{{ __('No restaurants available') }}</flux:heading>
            <flux:text class="text-neutral-500 dark:text-neutral-400">
                {{ __("You've been everywhere recently! Add more favorites or check back later.") }}
            </flux:text>
            <flux:button as="link" href="{{ route('restaurants.create') }}" wire:navigate variant="primary">
                {{ __('Add a restaurant') }}
            </flux:button>
        </div>
    @endif

</div>
