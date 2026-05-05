<?php

use App\Models\Restaurant;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Restaurant')] class extends Component {
    public Restaurant $restaurant;

    public function mount(Restaurant $restaurant): void
    {
        $this->authorize('view', $restaurant);
        $this->restaurant = $restaurant;
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->restaurant);
        $this->restaurant->delete();
        Flux::toast(variant: 'success', text: __('Restaurant deleted.'));
        $this->redirect(route('restaurants.index'), navigate: true);
    }
}; ?>

<div class="w-full">
    <section class="w-full space-y-6 pb-24">
        <flux:heading size="xl">{{ $this->restaurant->name }}</flux:heading>

        @if ($this->restaurant->address)
            <flux:text class="text-neutral-500 dark:text-neutral-400">{{ $this->restaurant->address }}</flux:text>
        @endif

        @if (!empty($this->restaurant->cuisine_tags))
            <div>
                <flux:label class="mb-1">{{ __('Cuisine') }}</flux:label>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->restaurant->cuisine_tags as $tag)
                        <flux:badge size="sm">{{ $tag }}</flux:badge>
                    @endforeach
                </div>
            </div>
        @endif

        @if (!empty($this->restaurant->vibe_tags))
            <div>
                <flux:label class="mb-1">{{ __('Vibe') }}</flux:label>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->restaurant->vibe_tags as $tag)
                        <flux:badge size="sm" variant="pill">{{ $tag }}</flux:badge>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($this->restaurant->price_level !== null)
            <div>
                <flux:label class="mb-1">{{ __('Price') }}</flux:label>
                <flux:text>{{ str_repeat('$', $this->restaurant->price_level) }}</flux:text>
            </div>
        @endif

        @if ($this->restaurant->patio_quality !== null)
            <div>
                <flux:label class="mb-1">{{ __('Patio') }}</flux:label>
                <flux:text>{{ $this->restaurant->patio_quality->value }}</flux:text>
            </div>
        @endif

        @if ($this->restaurant->indoor_vibe_when_cold !== null)
            <div>
                <flux:label class="mb-1">{{ __('Indoor vibe') }}</flux:label>
                <flux:text>{{ $this->restaurant->indoor_vibe_when_cold->value }}</flux:text>
            </div>
        @endif

        <div>
            <flux:label class="mb-1">{{ __('Visits') }}</flux:label>
            <flux:text>{{ $this->restaurant->visit_count }}</flux:text>
        </div>

        <div>
            <flux:label class="mb-1">{{ __('Last visited') }}</flux:label>
            <flux:text>
                {{ $this->restaurant->last_visited_at ? $this->restaurant->last_visited_at->format('j M Y') : __('Never') }}
            </flux:text>
        </div>
    </section>

    {{-- Thumb zone: fixed bottom bar --}}
    <div class="fixed bottom-0 left-0 right-0 z-10 flex gap-3 border-t border-neutral-200 bg-white px-4 py-3 dark:border-neutral-700 dark:bg-neutral-900">
        <flux:button as="link" href="{{ route('restaurants.edit', $this->restaurant) }}" wire:navigate class="flex-1">
            {{ __('Edit') }}
        </flux:button>

        <flux:modal.trigger name="delete-restaurant">
            <flux:button variant="danger" class="flex-1">
                {{ __('Delete') }}
            </flux:button>
        </flux:modal.trigger>
    </div>

    <flux:modal name="delete-restaurant" class="md:w-96">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Delete restaurant?') }}</flux:heading>
            <flux:text>{{ __('This action cannot be undone.') }}</flux:text>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button>{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button wire:click="delete" variant="danger">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
