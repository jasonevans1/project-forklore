<?php

use App\Enums\IndoorVibe;
use App\Enums\PatioQuality;
use App\Enums\RestaurantSource;
use App\Models\Restaurant;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Add restaurant')] class extends Component {
    public string $name = '';

    public string $address = '';

    public string $cuisine_tags = '';

    public string $vibe_tags = '';

    public ?int $price_level = null;

    public string $patio_quality = PatioQuality::None->value;

    public string $indoor_vibe_when_cold = IndoorVibe::Neutral->value;

    public ?int $avg_duration_minutes = null;

    /**
     * Save the new restaurant.
     */
    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'cuisine_tags' => ['required', 'string', 'max:500'],
            'vibe_tags' => ['required', 'string', 'max:500'],
            'price_level' => ['nullable', 'integer', 'between:1,4'],
            'patio_quality' => ['required', Rule::enum(PatioQuality::class)],
            'indoor_vibe_when_cold' => ['required', Rule::enum(IndoorVibe::class)],
            'avg_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
        ]);

        $cuisineTags = $this->splitTags($this->cuisine_tags);
        $vibeTags = $this->splitTags($this->vibe_tags);

        if (empty($cuisineTags)) {
            $this->addError('cuisine_tags', __('At least one cuisine tag is required.'));

            return;
        }

        if (empty($vibeTags)) {
            $this->addError('vibe_tags', __('At least one vibe tag is required.'));

            return;
        }

        Restaurant::create([
            'owner_user_id' => Auth::id(),
            'name' => $this->name,
            'address' => $this->address ?: null,
            'cuisine_tags' => $cuisineTags,
            'vibe_tags' => $vibeTags,
            'price_level' => $this->price_level,
            'patio_quality' => $this->patio_quality,
            'indoor_vibe_when_cold' => $this->indoor_vibe_when_cold,
            'avg_duration_minutes' => $this->avg_duration_minutes,
            'source' => RestaurantSource::Favorite,
            'lat' => null,
            'lng' => null,
            'last_visited_at' => null,
            'visit_count' => 0,
        ]);

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

        <form wire:submit="save" class="space-y-6" novalidate>
            {{-- Name --}}
            <flux:input
                wire:model="name"
                :label="__('Name')"
                type="text"
                required
                autofocus
                autocomplete="off"
            />

            {{-- Address --}}
            <flux:input
                wire:model="address"
                :label="__('Address')"
                type="text"
                autocomplete="off"
            />

            {{-- Cuisine Tags --}}
            <flux:input
                wire:model="cuisine_tags"
                :label="__('Cuisine tags')"
                :description="__('Comma-separated (e.g. Italian, Pizza)')"
                type="text"
                required
            />

            {{-- Vibe Tags --}}
            <flux:input
                wire:model="vibe_tags"
                :label="__('Vibe tags')"
                :description="__('Comma-separated (e.g. romantic, casual)')"
                type="text"
                required
            />

            {{-- Price Level --}}
            <flux:select wire:model="price_level" :label="__('Price level')">
                <flux:select.option value="">{{ __('— Select —') }}</flux:select.option>
                <flux:select.option value="1">{{ __('$ – Inexpensive') }}</flux:select.option>
                <flux:select.option value="2">{{ __('$$ – Moderate') }}</flux:select.option>
                <flux:select.option value="3">{{ __('$$$ – Expensive') }}</flux:select.option>
                <flux:select.option value="4">{{ __('$$$$ – Very expensive') }}</flux:select.option>
            </flux:select>

            {{-- Patio Quality --}}
            <flux:select wire:model="patio_quality" :label="__('Patio quality')" required>
                @foreach (PatioQuality::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ ucfirst($case->value) }}</flux:select.option>
                @endforeach
            </flux:select>

            {{-- Indoor Vibe When Cold --}}
            <flux:select wire:model="indoor_vibe_when_cold" :label="__('Indoor vibe when cold')" required>
                @foreach (IndoorVibe::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ ucfirst($case->value) }}</flux:select.option>
                @endforeach
            </flux:select>

            {{-- Avg Duration Minutes --}}
            <flux:input
                wire:model="avg_duration_minutes"
                :label="__('Average visit duration (minutes)')"
                type="number"
                min="1"
                max="600"
            />

            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary">
                    {{ __('Add restaurant') }}
                </flux:button>
            </div>
        </form>
    </div>
</section>
