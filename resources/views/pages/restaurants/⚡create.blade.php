<?php

use App\Actions\ProfilePlacesRestaurant;
use App\Enums\IndoorVibe;
use App\Enums\PatioQuality;
use App\Enums\PrimaryCuisine;
use App\Enums\RestaurantSource;
use App\Enums\ServiceLevel;
use App\Enums\ServiceOption;
use App\Models\Restaurant;
use App\Services\PlacesService;
use Flux\Flux;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Add restaurant')] class extends Component {
    public string $activeTab = 'search';

    public string $searchQuery = '';

    /** @var array<int, array<string, mixed>>|null */
    public ?array $searchResults = null;

    public string $name = '';

    public string $address = '';

    public string $cuisine_tags = '';

    public array $vibe_tags = [];

    public ?int $price_level = null;

    public string $patio_quality = PatioQuality::None->value;

    public string $indoor_vibe_when_cold = IndoorVibe::Neutral->value;

    public ?int $avg_duration_minutes = null;

    public ?string $service_level = null;

    public array $service_options = [];

    public ?string $primary_cuisine = null;

    public ?float $lat = null;

    public ?float $lng = null;

    public ?string $places_id = null;

    /**
     * Search Google Places with the entered query.
     */
    public function search(PlacesService $places): void
    {
        $results = $places->textSearch($this->searchQuery);

        $this->searchResults = $results ?? [];
    }

    /**
     * Pre-fill the form from a selected Places result and switch to the manual tab.
     */
    public function selectPlace(int $index): void
    {
        $place = $this->searchResults[$index] ?? null;

        if ($place === null) {
            return;
        }

        $this->name = $place['name'] ?? '';
        $this->address = $place['address'] ?? '';
        $this->price_level = $place['price_level'] ?? null;
        $this->lat = isset($place['lat']) ? (float) $place['lat'] : null;
        $this->lng = isset($place['lng']) ? (float) $place['lng'] : null;
        $this->places_id = $place['id'] ?? null;
        $this->cuisine_tags = implode(', ', PlacesService::cuisineTagsFromTypes($place['types'] ?? []));

        $this->activeTab = 'manual';
    }

    /**
     * Save a Places result as a favorite in one tap, then defer full profiling.
     */
    public function quickAdd(int $index): void
    {
        $place = $this->searchResults[$index] ?? null;

        if ($place === null) {
            return;
        }

        $validator = Validator::make($place, [
            'id' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'price_level' => ['nullable', 'integer', 'between:1,4'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string'],
        ]);

        if ($validator->fails()) {
            Flux::toast(variant: 'danger', text: __('That search result could not be added.'));

            return;
        }

        $existing = Restaurant::where('places_id', $place['id'])->first();

        if ($existing !== null) {
            $this->claimOrReject($existing);

            return;
        }

        try {
            $restaurant = Restaurant::create([
                'owner_user_id' => Auth::id(),
                'name' => $place['name'],
                'address' => ($place['address'] ?? null) ?: null,
                'cuisine_tags' => PlacesService::cuisineTagsFromTypes($place['types'] ?? []) ?: ['restaurant'],
                'vibe_tags' => [],
                'price_level' => $place['price_level'] ?? null,
                'source' => RestaurantSource::Favorite,
                'lat' => $place['lat'] ?? null,
                'lng' => $place['lng'] ?? null,
                'places_id' => $place['id'],
                'last_visited_at' => null,
                'visit_count' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->claimOrReject(Restaurant::where('places_id', $place['id'])->first());

            return;
        }

        $this->finishQuickAdd($restaurant);
    }

    /**
     * Turn the user's own Quick Pick-discovered row into a favorite; otherwise report a duplicate.
     */
    private function claimOrReject(?Restaurant $existing): void
    {
        if ($existing === null || $existing->source !== RestaurantSource::Places || $existing->owner_user_id !== Auth::id()) {
            Flux::toast(variant: 'danger', text: __('This restaurant is already in the system.'));

            return;
        }

        $existing->update(['source' => RestaurantSource::Favorite]);

        $this->finishQuickAdd($existing);
    }

    /**
     * Defer profiling (if still needed), toast, and go to the restaurant.
     */
    private function finishQuickAdd(Restaurant $restaurant): void
    {
        if ($restaurant->profiled_at === null) {
            defer(fn () => rescue(fn () => app(ProfilePlacesRestaurant::class)->execute($restaurant), report: true), name: "profile-restaurant-{$restaurant->id}");
        }

        Flux::toast(variant: 'success', text: __('Restaurant added.'));

        $this->redirect(route('restaurants.show', $restaurant), navigate: true);
    }

    /**
     * Save the new restaurant.
     */
    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'cuisine_tags' => ['required', 'string', 'max:500'],
            'vibe_tags' => ['array'],
            'vibe_tags.*' => [Rule::in(\Illuminate\Support\Arr::flatten(config('vibes')))],
            'price_level' => ['nullable', 'integer', 'between:1,4'],
            'patio_quality' => ['required', Rule::enum(PatioQuality::class)],
            'indoor_vibe_when_cold' => ['required', Rule::enum(IndoorVibe::class)],
            'avg_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'service_level' => ['nullable', Rule::enum(ServiceLevel::class)],
            'service_options' => ['nullable', 'array'],
            'service_options.*' => [Rule::enum(ServiceOption::class)],
            'primary_cuisine' => ['nullable', Rule::enum(PrimaryCuisine::class)],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'places_id' => ['nullable', 'string', 'max:255'],
        ]);

        $cuisineTags = $this->splitTags($this->cuisine_tags);

        if (empty($cuisineTags)) {
            $this->addError('cuisine_tags', __('At least one cuisine tag is required.'));

            return;
        }

        // Guard against duplicate places_id (global unique constraint)
        if ($this->places_id !== null && Restaurant::where('places_id', $this->places_id)->exists()) {
            $this->addError('name', __('This restaurant is already in the system.'));

            return;
        }

        $restaurant = Restaurant::create([
            'owner_user_id' => Auth::id(),
            'name' => $this->name,
            'address' => $this->address ?: null,
            'cuisine_tags' => $cuisineTags,
            'vibe_tags' => $this->vibe_tags,
            'price_level' => $this->price_level,
            'patio_quality' => $this->patio_quality,
            'indoor_vibe_when_cold' => $this->indoor_vibe_when_cold,
            'avg_duration_minutes' => $this->avg_duration_minutes,
            'service_level' => $this->service_level ?: null,
            'service_options' => $this->service_options ?: null,
            'primary_cuisine' => $this->primary_cuisine ?: null,
            'source' => RestaurantSource::Favorite,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'places_id' => $this->places_id,
            'last_visited_at' => null,
            'visit_count' => 0,
        ]);

        defer(fn () => rescue(fn () => app(ProfilePlacesRestaurant::class)->execute($restaurant, onlyEmptyFields: true), report: true), name: "profile-restaurant-{$restaurant->id}");

        Flux::toast(variant: 'success', text: __('Restaurant added.'));

        $this->redirect(route('restaurants.index'), navigate: true);
    }

    /**
     * Split a comma-separated string into a trimmed, filtered array of tags.
     *
     * @return list<string>
     */
    private function splitTags(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->values()
            ->all();
    }
}; ?>

<section class="w-full">
    <div class="mx-auto max-w-lg px-4 py-6">
        <flux:heading size="xl" class="mb-6">{{ __('Add restaurant') }}</flux:heading>

        {{-- Tab switcher --}}
        <div class="mb-6 flex border-b border-zinc-200 dark:border-zinc-700">
            <button
                type="button"
                wire:click="$set('activeTab', 'search')"
                class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'search' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-zinc-500 hover:text-zinc-700' }}"
            >
                {{ __('Search Google') }}
            </button>
            <button
                type="button"
                wire:click="$set('activeTab', 'manual')"
                class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'manual' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-zinc-500 hover:text-zinc-700' }}"
            >
                {{ __('Add manually') }}
            </button>
        </div>

        {{-- Search Tab --}}
        @if ($activeTab === 'search')
            <div class="space-y-4">
                <form wire:submit="search" class="flex gap-2">
                    <flux:input
                        wire:model="searchQuery"
                        :placeholder="__('Search restaurants…')"
                        type="search"
                        class="flex-1"
                        autofocus
                    />
                    <flux:button type="submit" variant="primary">{{ __('Search') }}</flux:button>
                </form>

                @if ($searchResults !== null)
                    @if (count($searchResults) === 0)
                        <p class="text-sm text-zinc-500">{{ __('No results found') }}</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($searchResults as $i => $place)
                                <flux:card class="cursor-pointer p-4" wire:click="selectPlace({{ $i }})">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-zinc-900 dark:text-white">{{ $place['name'] }}</p>
                                            <p class="mt-0.5 truncate text-sm text-zinc-500">{{ $place['address'] }}</p>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-1">
                                            <flux:button size="sm" variant="ghost" class="min-h-11" wire:click.stop="selectPlace({{ $i }})">
                                                {{ __('Details') }}
                                            </flux:button>
                                            <flux:button size="sm" variant="primary" class="min-h-11" wire:click.stop="quickAdd({{ $i }})" wire:loading.attr="disabled">
                                                {{ __('Add') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                </flux:card>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        @endif

        {{-- Manual Tab --}}
        @if ($activeTab === 'manual')
            <form wire:submit="save" class="space-y-6" novalidate>
                <x-restaurants.form-fields />

                <div class="flex items-center gap-4">
                    <flux:button type="submit" variant="primary">
                        {{ __('Add restaurant') }}
                    </flux:button>
                </div>
            </form>
        @endif
    </div>
</section>
