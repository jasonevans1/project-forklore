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

    public array $vibe_tags = [];

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
            'vibe_tags' => ['required', 'array', 'min:1'],
            'vibe_tags.*' => [Rule::in(\Illuminate\Support\Arr::flatten(config('vibes')))],
            'price_level' => ['nullable', 'integer', 'between:1,4'],
            'patio_quality' => ['required', Rule::enum(PatioQuality::class)],
            'indoor_vibe_when_cold' => ['required', Rule::enum(IndoorVibe::class)],
            'avg_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
        ]);

        $cuisineTags = $this->splitTags($this->cuisine_tags);

        if (empty($cuisineTags)) {
            $this->addError('cuisine_tags', __('At least one cuisine tag is required.'));

            return;
        }

        Restaurant::create([
            'owner_user_id' => Auth::id(),
            'name' => $this->name,
            'address' => $this->address ?: null,
            'cuisine_tags' => $cuisineTags,
            'vibe_tags' => $this->vibe_tags,
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
            <x-restaurants.form-fields />

            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary">
                    {{ __('Add restaurant') }}
                </flux:button>
            </div>
        </form>
    </div>
</section>
