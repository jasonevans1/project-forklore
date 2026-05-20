<?php

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Events')] class extends Component {
    public Restaurant $restaurant;

    public function mount(Restaurant $restaurant): void
    {
        $this->authorize('view', $restaurant);
        $this->restaurant = $restaurant;
    }

    /**
     * Get all events for this restaurant ordered by title.
     *
     * @return Collection<int, \App\Models\Event>
     */
    #[Computed]
    public function events(): Collection
    {
        return $this->restaurant->events()->orderBy('title')->get();
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Events') }}</flux:heading>
            <flux:text class="text-neutral-500 dark:text-neutral-400">
                {{ $this->restaurant->name }}
            </flux:text>
        </div>

        <flux:button as="link" href="{{ route('restaurants.events.create', $this->restaurant) }}" wire:navigate variant="primary" size="sm">
            {{ __('Add event') }}
        </flux:button>
    </div>

    @if ($this->events->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-neutral-300 px-6 py-16 text-center dark:border-neutral-700">
            <flux:heading size="lg" class="mb-2">{{ __('No events yet') }}</flux:heading>
            <flux:text class="mb-6 text-neutral-500 dark:text-neutral-400">
                {{ __('Add trivia nights, live music, and more.') }}
            </flux:text>
            <flux:button as="link" href="{{ route('restaurants.events.create', $this->restaurant) }}" wire:navigate variant="primary">
                {{ __('Add event') }}
            </flux:button>
        </div>
    @else
        <div class="flex flex-col gap-4">
            @foreach ($this->events as $event)
                <flux:card class="w-full">
                    <div class="space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <a href="{{ route('restaurants.events.edit', [$this->restaurant, $event]) }}" wire:navigate>
                                <flux:heading size="lg">{{ $event->title }}</flux:heading>
                            </a>

                            @if (! $event->active)
                                <flux:badge variant="pill" size="sm" color="zinc">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge size="sm">{{ $event->type->value }}</flux:badge>
                            <flux:badge size="sm" variant="pill">{{ $event->recurrence->value }}</flux:badge>
                        </div>

                        <flux:text class="text-neutral-500 dark:text-neutral-400">
                            {{ $event->start_time }} – {{ $event->end_time }}
                        </flux:text>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif
</section>
