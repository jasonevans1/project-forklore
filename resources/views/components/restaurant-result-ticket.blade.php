@props([
    'restaurant',
    'badgeLabel',
    'eventLabel' => null,
    'tagline' => null,
    'distanceLabel' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-4']) }}>
    <flux:badge size="sm" class="self-start">
        {{ $badgeLabel }}
    </flux:badge>

    @if ($eventLabel)
        <flux:text class="text-base font-semibold text-zinc-500 dark:text-zinc-400">
            {{ $eventLabel }}
        </flux:text>
    @endif

    <flux:heading size="xl" class="text-3xl font-bold leading-tight" style="font-family: var(--font-display);">
        {{ $restaurant->name }}
    </flux:heading>

    @if (! empty($restaurant->cuisine_tags))
        <div class="cuisine-tags flex flex-wrap gap-2">
            @foreach ($restaurant->cuisine_tags as $tag)
                <flux:badge>{{ $tag }}</flux:badge>
            @endforeach
        </div>
    @endif

    @if ($restaurant->price_level !== null || $distanceLabel !== null)
        <div class="flex items-center gap-3 text-sm text-neutral-500 dark:text-neutral-400" style="font-family: var(--font-mono-ticket);">
            @if ($restaurant->price_level !== null)
                <span>{{ str_repeat('$', $restaurant->price_level) }}</span>
            @endif

            @if ($distanceLabel !== null)
                @if ($restaurant->price_level !== null)
                    <span aria-hidden="true">&middot;</span>
                @endif
                <span>{{ $distanceLabel }}</span>
            @endif
        </div>
    @endif

    @if ($tagline)
        <flux:text class="text-base italic text-neutral-600 dark:text-neutral-300">
            {{ $tagline }}
        </flux:text>
    @endif

    @if ($restaurant->address)
        <flux:text class="text-sm text-neutral-500 dark:text-neutral-400">
            {{ $restaurant->address }}
        </flux:text>
    @endif
</div>
