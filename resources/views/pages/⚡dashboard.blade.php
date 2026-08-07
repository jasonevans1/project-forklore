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

    {{-- Decision mode cards --}}
    <section class="space-y-3">
        <flux:heading size="lg" class="font-display uppercase">{{ __('Pick a mode') }}</flux:heading>

        <div class="flex flex-col divide-y divide-dashed divide-ticket-line border-t border-b border-dashed border-ticket-line">
            @foreach ([
                ['number' => '01', 'route' => 'pick', 'icon' => 'bolt', 'name' => __('Quick Pick'), 'description' => __('Weather-aware, one-tap pick from your favorites')],
                ['number' => '02', 'route' => 'tonight', 'icon' => 'calendar', 'name' => __('Tonight'), 'description' => __('Find a spot with something happening tonight')],
                ['number' => '03', 'route' => 'quiz', 'icon' => 'question-mark-circle', 'name' => __('Guided Quiz'), 'description' => __('Answer 5 questions to find your best match')],
                ['number' => '04', 'route' => 'tournament', 'icon' => 'trophy', 'name' => __('Tournament'), 'description' => __('Head-to-head bracket until one winner remains')],
            ] as $mode)
                <a href="{{ route($mode['route']) }}" wire:navigate
                   class="flex items-baseline gap-4 py-4 hover:bg-ticket-bg">
                    <span class="text-sm text-accent font-mono-ticket">{{ $mode['number'] }}</span>
                    <flux:icon name="{{ $mode['icon'] }}" class="size-5 shrink-0 self-center text-accent" />
                    <div>
                        <flux:heading size="sm" class="font-display uppercase">{{ $mode['name'] }}</flux:heading>
                        <flux:text class="text-xs text-ink/70">{{ $mode['description'] }}</flux:text>
                    </div>
                </a>
            @endforeach
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
                    <x-ticket-row :name="$restaurant->name" :href="route('restaurants.show', $restaurant)">
                        @if (! empty($restaurant->cuisine_tags))
                            {{ implode(', ', $restaurant->cuisine_tags) }}
                        @endif

                        <x-slot:trailing>
                            <flux:icon name="chevron-right" class="size-4 shrink-0" />
                        </x-slot:trailing>
                    </x-ticket-row>
                @endforeach

                <flux:button as="link" href="{{ route('restaurants.index') }}" wire:navigate variant="ghost" size="sm" class="self-start">
                    {{ __('View all restaurants') }}
                </flux:button>
            </div>
        @endif
    </section>

</div>
