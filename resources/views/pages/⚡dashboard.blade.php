<?php

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    /**
     * Get the authenticated user's 3 most recently added restaurants.
     *
     * @return Collection<int, Restaurant>
     */
    #[Computed]
    public function recentRestaurants(): Collection
    {
        return Restaurant::ownedBy(Auth::user())->latest()->limit(3)->get();
    }
}; ?>

<div class="w-full space-y-8">

    {{-- Page heading --}}
    <flux:heading size="xl">{{ __('What are we doing tonight?') }}</flux:heading>

    {{-- Decision mode cards: 2-column grid --}}
    <section class="space-y-3">
        <flux:heading size="lg">{{ __('Pick a mode') }}</flux:heading>

        <div class="grid grid-cols-2 gap-3">

            {{-- Quick Pick --}}
            <a href="{{ route('pick') }}" wire:navigate
               class="flex flex-col gap-2 rounded-xl border border-neutral-200 p-4 hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800">
                <flux:icon name="bolt" class="size-6 text-amber-500" />
                <flux:heading size="sm">{{ __('Quick Pick') }}</flux:heading>
                <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">
                    {{ __('Weather-aware, one-tap pick from your favorites') }}
                </flux:text>
            </a>

            {{-- Tonight --}}
            <a href="{{ route('tonight') }}" wire:navigate
               class="flex flex-col gap-2 rounded-xl border border-neutral-200 p-4 hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800">
                <flux:icon name="calendar" class="size-6 text-blue-500" />
                <flux:heading size="sm">{{ __('Tonight') }}</flux:heading>
                <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">
                    {{ __('Find a spot with something happening tonight') }}
                </flux:text>
            </a>

            {{-- Quiz --}}
            <a href="{{ route('quiz') }}" wire:navigate
               class="flex flex-col gap-2 rounded-xl border border-neutral-200 p-4 hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800">
                <flux:icon name="question-mark-circle" class="size-6 text-purple-500" />
                <flux:heading size="sm">{{ __('Guided Quiz') }}</flux:heading>
                <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">
                    {{ __('Answer 5 questions to find your best match') }}
                </flux:text>
            </a>

            {{-- Tournament --}}
            <a href="{{ route('tournament') }}" wire:navigate
               class="flex flex-col gap-2 rounded-xl border border-neutral-200 p-4 hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800">
                <flux:icon name="trophy" class="size-6 text-yellow-500" />
                <flux:heading size="sm">{{ __('Tournament') }}</flux:heading>
                <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">
                    {{ __('Head-to-head bracket until one winner remains') }}
                </flux:text>
            </a>

        </div>
    </section>

    {{-- Recently added restaurants --}}
    <section class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Recently Added') }}</flux:heading>
            <flux:button as="link" href="{{ route('restaurants.create') }}" wire:navigate variant="ghost" size="sm">
                {{ __('Add restaurant') }}
            </flux:button>
        </div>

        @if ($this->recentRestaurants->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-neutral-300 px-6 py-10 text-center dark:border-neutral-700">
                <flux:text class="mb-4 text-neutral-500 dark:text-neutral-400">
                    {{ __("You haven't added any restaurants yet.") }}
                </flux:text>
                <flux:button as="link" href="{{ route('restaurants.create') }}" wire:navigate variant="primary" size="sm">
                    {{ __('Add your first restaurant') }}
                </flux:button>
            </div>
        @else
            <div class="flex flex-col gap-3">
                @foreach ($this->recentRestaurants as $restaurant)
                    <a href="{{ route('restaurants.show', $restaurant) }}" wire:navigate
                       class="flex items-center justify-between rounded-xl border border-neutral-200 px-4 py-3 hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800">
                        <div>
                            <flux:heading size="sm">{{ $restaurant->name }}</flux:heading>
                            @if (! empty($restaurant->cuisine_tags))
                                <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ implode(', ', $restaurant->cuisine_tags) }}
                                </flux:text>
                            @endif
                        </div>
                        <flux:icon name="chevron-right" class="size-4 shrink-0 text-neutral-400" />
                    </a>
                @endforeach

                <flux:button as="link" href="{{ route('restaurants.index') }}" wire:navigate variant="ghost" size="sm" class="self-start">
                    {{ __('View all restaurants') }}
                </flux:button>
            </div>
        @endif
    </section>

</div>
