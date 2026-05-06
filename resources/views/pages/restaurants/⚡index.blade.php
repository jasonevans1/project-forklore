<?php

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Restaurants')] class extends Component {
    /**
     * Get the authenticated user's restaurants ordered by name.
     *
     * @return Collection<int, Restaurant>
     */
    #[Computed]
    public function restaurants(): Collection
    {
        return Restaurant::ownedBy(Auth::user())->orderBy('name')->get();
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Restaurants') }}</flux:heading>

        <flux:button as="link" href="{{ route('restaurants.create') }}" wire:navigate variant="primary" size="sm">
            {{ __('Add restaurant') }}
        </flux:button>
    </div>

    @if ($this->restaurants->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-neutral-300 px-6 py-16 text-center dark:border-neutral-700">
            <flux:heading size="lg" class="mb-2">{{ __('No restaurants yet') }}</flux:heading>
            <flux:text class="mb-6 text-neutral-500 dark:text-neutral-400">
                {{ __('Add your first restaurant to get started.') }}
            </flux:text>
            <flux:button as="link" href="{{ route('restaurants.create') }}" wire:navigate variant="primary">
                {{ __('Add restaurant') }}
            </flux:button>
        </div>
    @else
        <div class="flex flex-col gap-4">
            @foreach ($this->restaurants as $restaurant)
                <flux:card class="w-full">
                    <div class="space-y-3">
                        <a href="{{ route('restaurants.show', $restaurant) }}" wire:navigate>
                            <flux:heading size="lg">{{ $restaurant->name }}</flux:heading>
                        </a>

                        @if (!empty($restaurant->cuisine_tags))
                            <div class="flex flex-wrap gap-2">
                                @foreach ($restaurant->cuisine_tags as $tag)
                                    <flux:badge size="sm">{{ $tag }}</flux:badge>
                                @endforeach
                            </div>
                        @endif

                        @if ($restaurant->price_level !== null)
                            <flux:text class="text-neutral-500 dark:text-neutral-400">
                                {{ str_repeat('$', $restaurant->price_level) }}
                            </flux:text>
                        @endif
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif
</section>
