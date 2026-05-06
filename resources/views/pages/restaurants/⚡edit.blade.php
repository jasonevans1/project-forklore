<?php

use App\Enums\IndoorVibe;
use App\Enums\PatioQuality;
use App\Models\Restaurant;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit restaurant')] class extends Component {
    public int $restaurantId;

    public string $name = '';

    public string $address = '';

    public string $cuisine_tags = '';

    public string $vibe_tags = '';

    public ?int $price_level = null;

    public string $patio_quality = PatioQuality::None->value;

    public string $indoor_vibe_when_cold = IndoorVibe::Neutral->value;

    public ?int $avg_duration_minutes = null;

    public function mount(Restaurant $restaurant): void
    {
        $this->authorize('update', $restaurant);

        $this->restaurantId = $restaurant->id;
        $this->name = $restaurant->name;
        $this->address = $restaurant->address ?? '';
        $this->cuisine_tags = implode(', ', $restaurant->cuisine_tags ?? []);
        $this->vibe_tags = implode(', ', $restaurant->vibe_tags ?? []);
        $this->price_level = $restaurant->price_level;
        $this->patio_quality = $restaurant->patio_quality->value;
        $this->indoor_vibe_when_cold = $restaurant->indoor_vibe_when_cold->value;
        $this->avg_duration_minutes = $restaurant->avg_duration_minutes;
    }

    /**
     * Save the updated restaurant.
     */
    public function save(): void
    {
        $restaurant = Restaurant::findOrFail($this->restaurantId);

        $this->authorize('update', $restaurant);

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

        $restaurant->update([
            'name' => $this->name,
            'address' => $this->address ?: null,
            'cuisine_tags' => $cuisineTags,
            'vibe_tags' => $vibeTags,
            'price_level' => $this->price_level,
            'patio_quality' => $this->patio_quality,
            'indoor_vibe_when_cold' => $this->indoor_vibe_when_cold,
            'avg_duration_minutes' => $this->avg_duration_minutes,
        ]);

        Flux::toast(variant: 'success', text: __('Restaurant updated.'));

        $this->redirect(route('restaurants.show', $restaurant), navigate: true);
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
        <flux:heading size="xl" class="mb-6">{{ __('Edit') }} {{ $this->name }}</flux:heading>

        <form wire:submit="save" class="space-y-6" novalidate>
            <x-restaurants.form-fields />

            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary">
                    {{ __('Save changes') }}
                </flux:button>

                <flux:button :href="route('restaurants.show', $this->restaurantId)" wire:navigate variant="ghost">
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
</section>
